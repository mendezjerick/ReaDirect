<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import {
    Home, BookOpen, Trophy, HelpCircle, ClipboardList, ChevronDown,
    Menu, Settings, X, GraduationCap, Star, Flame, Gift, Target, Check, Lock,
} from 'lucide-vue-next';

const props = defineProps({
    learner:            { type: Object, default: null },
    modules:            { type: Array,  default: () => [] },
    latestAttempt:      { type: Object, default: null },
    latestFinalAttempt: { type: Object, default: null },
    flowState:          { type: Object, default: null },
    listeningMode:      { type: Object, default: () => ({ current: 'manual' }) },
    rewards:            { type: Object, default: () => ({ stars: 0 }) },
});

/* ── Dropdown Menu & Animations ───────────────────────── */
const sidebarOpen = ref(false);
const isBurgerAnimating = ref(false);
const isMounted = ref(false);

const openSidebar = () => {
    if (sidebarOpen.value || isBurgerAnimating.value) return;
    isBurgerAnimating.value = true;
    setTimeout(() => {
        sidebarOpen.value = true;
        isBurgerAnimating.value = false;
    }, 450);
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

// Close when clicking outside
const clickOutsideMenu = (e) => {
    if (sidebarOpen.value && !e.target.closest('.rd-menu-container')) {
        closeSidebar();
    }
};

onMounted(() => {
    setTimeout(() => { isMounted.value = true; }, 50);
    document.addEventListener('mousedown', clickOutsideMenu);
});
onUnmounted(() => {
    document.removeEventListener('mousedown', clickOutsideMenu);
});

/* ── Identity ───────────────────────────────────── */
const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const initial   = computed(() => firstName.value.charAt(0).toUpperCase());

const currentStage       = computed(() => props.flowState?.stage ?? props.learner?.current_stage ?? 'new');
const isDone             = computed(() => props.flowState?.diagnostic?.is_completed === true);
const primaryActionRoute = computed(() => props.flowState?.primary_action_route ?? '/learner/diagnostic/start');
const totalStars         = computed(() => Number(props.rewards?.stars ?? 0));
const currentListeningMode = computed(() => props.listeningMode?.current ?? 'manual');

const listeningModeForm = useForm({ listening_mode: currentListeningMode.value });
watch(currentListeningMode, (m) => { listeningModeForm.listening_mode = m; });

/* ── Module meta ───────────────────────────────── */
const moduleMeta = {
    module_1: { title: 'Letter and Sound Learning', blurb: 'Learn the sounds that letters make and how to say them.' },
    module_2: { title: 'Word Recognition',          blurb: 'Practice reading and recognizing words quickly and clearly.' },
    module_3: { title: 'Reading Comprehension',     blurb: 'Read short passages and answer questions about them.' },
};
const metaFor = (key) => moduleMeta[key] ?? { title: 'Module', blurb: '' };

const assignedKey   = computed(() => props.learner?.current_module?.key ?? null);
const assignedTitle = computed(() => {
    if (assignedKey.value) return metaFor(assignedKey.value).title;
    if (currentStage.value === 'grade_ready') return 'No module needed';
    if (currentStage.value?.startsWith('final_reassessment')) return 'Final reassessment';
    return 'Take the diagnostic';
});

/* ── Scores ────────────────────────────────────── */
const lettersScore   = computed(() => Number(props.latestAttempt?.task_1_score ?? 0));
const sentencesScore = computed(() => Number(props.latestAttempt?.task_2b_score ?? props.latestAttempt?.task_2a_score ?? 0));
const passageScore   = computed(() => {
    const acc = props.latestAttempt?.reading_accuracy;
    if (acc == null) return 0;
    const n = Number(acc);
    return Math.round((n <= 1 ? n : n / 100) * 10);
});
const overallScore = computed(() => {
    if (!isDone.value) return 0;
    return Math.round(((lettersScore.value + sentencesScore.value + passageScore.value) / 30) * 100);
});
const overallLabel = computed(() => {
    if (!isDone.value) return 'Get started!';
    if (overallScore.value >= 70) return 'Great Job!';
    if (overallScore.value >= 40) return 'Good Job!';
    return 'Keep Going!';
});

/* ── Module cards ──────────────────────────────── */
const moduleCards = computed(() =>
    props.modules.map((m) => {
        const meta     = metaFor(m.key);
        const assigned = m.key === assignedKey.value &&
            !['grade_ready','final_reassessment_pending','final_reassessment_in_progress','final_reassessment_completed','completed'].includes(currentStage.value);
        return { key: m.key, sequence: m.sequence, title: meta.title, blurb: meta.blurb, assigned, locked: !assigned };
    })
);

/* ── Path nodes ────────────────────────────────── */
const pathNodes = computed(() => [
    {
        id: 'diagnostic', label: 'Start Diagnostic', sub: 'Begin your reading check', emoji: '✨',
        state: isDone.value ? 'completed'
             : (currentStage.value === 'new' || currentStage.value === 'diagnostic_in_progress') ? 'current'
             : 'completed',
        href: primaryActionRoute.value,
    },
    { id: 'letters',   label: 'Letters',         sub: 'Identifying letter names and sounds',                emoji: '🔤', state: isDone.value ? 'completed' : 'locked', href: null },
    { id: 'sentences', label: 'Sentences',        sub: 'Reading and understanding simple sentences',         emoji: '💬', state: isDone.value ? 'completed' : 'locked', href: null },
    { id: 'passage',   label: 'Reading Passage',  sub: 'Reading short passages and answering questions',     emoji: '📖', state: isDone.value ? 'completed' : 'locked', href: null },
    ...moduleCards.value.map((m, i) => ({
        id: m.key, label: m.title, sub: m.blurb,
        emoji: i === 0 ? '🔊' : i === 1 ? '🅰️' : '📘',
        state: m.assigned ? 'current' : m.locked ? 'locked' : 'available',
        href: m.assigned ? `/learner/modules/${m.key}/start` : null,
    })),
]);

/* ── Dynamic mascot positioning ─────────────────── */
const nodeRefs     = ref([]);
const mascotStyle  = ref({});
const pathWrapRef  = ref(null);

const setNodeRef = (el, idx) => { if (el) nodeRefs.value[idx] = el; };

const repositionMascot = () => {
    const currentIdx = pathNodes.value.findIndex(n => n.state === 'current');
    const idx        = currentIdx >= 0 ? currentIdx : 0;
    const el         = nodeRefs.value[idx];
    const wrap       = pathWrapRef.value;
    if (!el || !wrap) return;
    const elRect   = el.getBoundingClientRect();
    const wrapRect = wrap.getBoundingClientRect();
    const top      = elRect.top - wrapRect.top + elRect.height / 2 - 120;
    mascotStyle.value = { top: `${Math.max(0, top)}px` };
};

onMounted(async () => {
    await nextTick();
    repositionMascot();
    window.addEventListener('resize', repositionMascot);
});
onUnmounted(() => window.removeEventListener('resize', repositionMascot));
</script>

<template>
    <div class="rd-root" :class="{ 'is-loaded': isMounted }">

        <!-- ═══ TOP LEFT CONTROLS (Menu) ═══════════════════ -->
        <div class="rd-top-left-controls">
            <!-- Menu Container (Burger + Dropdown) -->
            <div class="rd-menu-container">
                <!-- Burger Button -->
                <button
                    class="rd-burger"
                    :class="{ 'is-animating': isBurgerAnimating, 'is-open': sidebarOpen }"
                    @click="sidebarOpen ? closeSidebar() : openSidebar()"
                    aria-label="Toggle menu"
                >
                    <div class="rd-burger-icon-wrap">
                        <Menu class="rd-icon-menu" :size="20" />
                        <Settings class="rd-icon-settings" :size="20" />
                    </div>
                </button>

                <!-- Animated magic trail to menu -->
                <div v-if="isBurgerAnimating" class="rd-magic-trail">
                    <span class="rd-magic-dot d1"></span>
                    <span class="rd-magic-dot d2"></span>
                    <span class="rd-magic-dot d3"></span>
                    <span class="rd-magic-sparkle">✨</span>
                </div>

                <!-- Dropdown Menu -->
                <Transition name="rd-dropdown">
                    <div v-if="sidebarOpen" class="rd-dropdown-panel">
                        <div class="rd-dropdown-head">
                            <span class="rd-dropdown-title">Menu</span>
                            <button class="rd-dropdown-close" @click="closeSidebar" aria-label="Close">
                                <X :size="16" />
                            </button>
                        </div>
                        <nav class="rd-dropdown-nav">
                            <a href="/learner/dashboard" class="rd-nav rd-nav--active" @click="closeSidebar">
                                <Home :size="18" /><span>Dashboard</span>
                            </a>
                            <a href="/learner/modules" class="rd-nav" @click="closeSidebar">
                                <BookOpen :size="18" /><span>My Learning</span>
                            </a>
                            <Link href="/learner/progress" class="rd-nav" @click="closeSidebar">
                                <ClipboardList :size="18" /><span>Progress</span>
                            </Link>
                            <Link href="/learner/rewards" class="rd-nav" @click="closeSidebar">
                                <Trophy :size="18" /><span>Rewards</span>
                            </Link>
                            <Link href="/learner/help" class="rd-nav" @click="closeSidebar">
                                <HelpCircle :size="18" /><span>Help</span>
                            </Link>
                        </nav>
                        <div class="rd-dropdown-footer">
                            <div class="rd-dropdown-footer-icon"><GraduationCap :size="16" /></div>
                            <p>Keep learning, keep growing!</p>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- ═══ TOP RIGHT CONTROLS (Profile) ═══════════════ -->
        <div class="rd-top-right-controls">
            <!-- Tester Profile Pill -->
            <div class="rd-user-pill">
                <div class="rd-user-avatar">{{ initial }}</div>
                <span class="rd-user-name">{{ firstName }}</span>
                <ChevronDown :size="14" class="rd-user-chevron" />
            </div>
        </div>

        <!-- ═══ PAGE BODY ══════════════════════════════════ -->
        <div class="rd-page">

            <!-- ── LEFT: Path + Mascot ─────────────────────────── -->
            <div class="rd-left">

                <!-- Greeting -->
                <div class="rd-greeting">
                    <div>
                        <h1 class="rd-greeting-title">Hi, {{ firstName }}! 👋</h1>
                        <p class="rd-greeting-sub">Keep going! Your reading adventure awaits.</p>
                    </div>
                    <Link :href="primaryActionRoute" class="rd-primary-btn">
                        ✨ {{ pathNodes[0]?.state === 'current' ? 'Start Diagnostic' : 'Continue' }}
                    </Link>
                </div>

                <!-- Status strip -->
                <div class="rd-status-strip">
                    <div class="rd-stat">
                        <span class="rd-stat-emoji">🏆</span>
                        <div>
                            <p class="rd-stat-lbl">Overall Score</p>
                            <p class="rd-stat-val">{{ overallScore }}%</p>
                            <p class="rd-stat-hint">{{ overallLabel }}</p>
                        </div>
                    </div>
                    <div class="rd-stat-div" />
                    <div class="rd-stat">
                        <span class="rd-stat-emoji">📚</span>
                        <div>
                            <p class="rd-stat-lbl">Assigned Module</p>
                            <p class="rd-stat-val rd-stat-val--sm">{{ assignedTitle }}</p>
                            <p class="rd-stat-hint">Current path</p>
                        </div>
                    </div>
                    <div class="rd-stat-div" />
                    <div class="rd-stat">
                        <span class="rd-stat-emoji">⭐</span>
                        <div>
                            <p class="rd-stat-lbl">Stars</p>
                            <p class="rd-stat-val">{{ totalStars }}</p>
                            <p class="rd-stat-hint">Earned rewards</p>
                        </div>
                    </div>
                    <div class="rd-stat-div" />
                    <div class="rd-stat">
                        <span class="rd-stat-emoji">🎙️</span>
                        <div>
                            <p class="rd-stat-lbl">Recording</p>
                            <p class="rd-stat-val rd-stat-val--sm">{{ currentListeningMode === 'automatic_ciel' ? 'Automatic' : 'Manual' }}</p>
                            <p class="rd-stat-hint">Current mode</p>
                        </div>
                    </div>
                </div>

                <!-- Map area: Mascot + Path -->
                <div class="rd-map" ref="pathWrapRef">

                    <!-- Mascot -->
                    <div class="rd-mascot" :style="mascotStyle">
                        <img
                            :src="'/images/mascot/mascot.png'"
                            alt="ReaDirect Mascot"
                            class="rd-mascot-img"
                        />
                        <div class="rd-speech-bubble">
                            Let's build your reading skills together!
                        </div>
                    </div>

                    <!-- Progress path -->
                    <div class="rd-path">
                        <template v-for="(node, idx) in pathNodes" :key="node.id">
                            <div v-if="idx > 0" class="rd-trail">
                                <span v-for="d in 4" :key="d" class="rd-dot"
                                      :class="{ 'rd-dot--completed': pathNodes[idx-1].state === 'completed' }"
                                      :style="`--dot-idx: ${d}`" />
                            </div>

                            <div
                                class="rd-node-row"
                                :class="[ idx % 2 === 0 ? 'rd-node-row--r' : 'rd-node-row--l', `anim-delay-${idx}` ]"
                                :ref="el => setNodeRef(el, idx)"
                            >
                                <div
                                    class="rd-label"
                                    :class="{
                                        'rd-label--current':   node.state === 'current',
                                        'rd-label--completed': node.state === 'completed',
                                        'rd-label--locked':    node.state === 'locked',
                                    }"
                                >
                                    <div class="rd-label-content">
                                        <p class="rd-label-title">{{ node.label }}</p>
                                        <p class="rd-label-sub">{{ node.sub }}</p>
                                    </div>
                                    <div v-if="node.state === 'locked'" class="rd-tooltip">
                                        Finish the previous activity first.
                                    </div>
                                </div>

                                <component
                                    :is="node.href ? Link : 'div'"
                                    :href="node.href || undefined"
                                    class="rd-node"
                                    :class="{
                                        'rd-node--current':   node.state === 'current',
                                        'rd-node--completed': node.state === 'completed',
                                        'rd-node--locked':    node.state === 'locked',
                                        'rd-node--available': node.state === 'available',
                                        'rd-node--clickable': !!node.href,
                                    }"
                                >
                                    <div v-if="node.state === 'current'" class="rd-node-sparkles">
                                        <span class="s1">✨</span><span class="s2">✨</span><span class="s3">✨</span>
                                    </div>

                                    <div v-if="node.state === 'completed'" class="rd-star-burst">
                                        <span class="burst1"></span><span class="burst2"></span>
                                        <span class="burst3"></span><span class="burst4"></span>
                                    </div>

                                    <div class="rd-node-face">
                                        <Check v-if="node.state === 'completed'" :size="28" class="rd-check-icon" />
                                        <div v-else-if="node.state === 'locked'" class="rd-lock-wrap">
                                            <Lock :size="20" class="rd-lock-icon" />
                                        </div>
                                        <span v-else class="rd-node-emoji">{{ node.emoji }}</span>
                                    </div>
                                </component>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Widgets ──────────────────────────────── -->
            <aside class="rd-widgets">
                <div class="rd-widget anim-widget-1">
                    <div class="rd-widget-hd">
                        <div class="rd-widget-icon" style="background: linear-gradient(135deg,#fb923c,#f59e0b)"><Flame :size="16" /></div>
                        <h2 class="rd-widget-title">Your Adventure</h2><span>✨</span>
                    </div>
                    <div class="rd-streaks">
                        <div class="rd-streak"><span class="rd-streak-icon">🔥</span><div><p class="rd-streak-lbl">Current Streak</p><p class="rd-streak-val">0 <span>days</span></p></div></div>
                        <div class="rd-streak"><span class="rd-streak-icon">🏅</span><div><p class="rd-streak-lbl">Best Streak</p><p class="rd-streak-val">0 <span>days</span></p></div></div>
                    </div>
                    <div class="rd-widget-note"><Star :size="13" style="color:#f59e0b;flex-shrink:0;margin-top:1px" /> Complete activities, earn stars, and unlock new lessons!</div>
                </div>

                <div class="rd-widget anim-widget-2">
                    <div class="rd-widget-hd">
                        <div class="rd-widget-icon" style="background: linear-gradient(135deg,#2dd4bf,#0d9488)"><Target :size="16" /></div>
                        <h2 class="rd-widget-title">Daily Goal</h2>
                    </div>
                    <p class="rd-goal-text">Finish 1 activity</p>
                    <div class="rd-goal-row"><div class="rd-goal-track"><div class="rd-goal-fill" style="width:0%" /></div><span class="rd-goal-cnt">0&thinsp;/&thinsp;1</span></div>
                </div>

                <div class="rd-widget anim-widget-3">
                    <div class="rd-widget-hd">
                        <div class="rd-widget-icon" style="background: linear-gradient(135deg,#fbbf24,#d97706)"><Gift :size="16" /></div>
                        <h2 class="rd-widget-title">Rewards</h2>
                    </div>
                    <p class="rd-rewards-sub">Earn stars to unlock rewards!</p>
                    <div class="rd-star-row"><Star :size="30" class="rd-star-icon" /><span class="rd-star-count">{{ totalStars }}</span></div>
                    <Link href="/learner/rewards" class="rd-view-rewards">View Rewards</Link>
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
/* ════════════════════════════════════════════════
   FONTS / ROOT
   ════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800;900&display=swap');

.rd-root {
    position: relative; min-height: 100vh;
    font-family: 'Nunito', system-ui, sans-serif;
    overflow-x: hidden;
    background-color: #f4e0ba;
    background-image: url('/images/backgrounds/learner-dashboard-desktop.png');
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important; animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important; scroll-behavior: auto !important;
    }
}

/* ════════════════════════════════════════════════
   TOP CONTROLS
   ════════════════════════════════════════════════ */
.rd-top-left-controls { position: fixed; top: 1.25rem; left: 1.25rem; z-index: 50; }
.rd-top-right-controls { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 50; }

/* Tester Pill */
.rd-user-pill {
    display: flex; align-items: center; gap: 0.45rem;
    padding: 0.22rem 0.9rem 0.22rem 0.22rem;
    background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
    border-radius: 9999px; border: 1px solid rgba(0,0,0,0.07);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.rd-user-avatar { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #F58549; color: white; font-size: 0.85rem; font-weight: 900; box-shadow: 0 2px 6px rgba(245,133,73,0.38); }
.rd-user-name { font-size: 0.88rem; font-weight: 800; color: #1e293b; }
.rd-user-chevron { color: #94a3b8; }

/* Menu Container */
.rd-menu-container { position: relative; }

/* Animated Burger */
.rd-burger {
    display: grid; place-items: center;
    width: 44px; height: 44px; border-radius: 12px;
    background: rgba(255,255,255,0.95); color: #374151;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.05);
    transition: box-shadow 140ms, transform 200ms; cursor: pointer;
    overflow: hidden; position: relative; z-index: 52;
}
.rd-burger:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.12); transform: scale(1.05); }
.rd-burger-icon-wrap { position: relative; width: 20px; height: 20px; }
.rd-icon-menu, .rd-icon-settings { position: absolute; inset: 0; transition: opacity 300ms, transform 450ms cubic-bezier(.4,0,.2,1); }
.rd-icon-menu { opacity: 1; transform: rotate(0deg) scale(1); }
.rd-icon-settings { opacity: 0; transform: rotate(-90deg) scale(0.5); }
.rd-burger.is-animating .rd-icon-menu, .rd-burger.is-open .rd-icon-menu { opacity: 0; transform: rotate(90deg) scale(0.5); }
.rd-burger.is-animating .rd-icon-settings, .rd-burger.is-open .rd-icon-settings { opacity: 1; transform: rotate(0deg) scale(1); color: #F58549; }

/* Magic trail during burger click */
.rd-magic-trail { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; z-index: 51; }
.rd-magic-dot { position: absolute; width: 6px; height: 6px; border-radius: 50%; background: #fcd34d; box-shadow: 0 0 4px #f59e0b; opacity: 0; }
.rd-magic-trail .d1 { top: -10px; left: 20px; animation: arc1 400ms ease-out forwards; }
.rd-magic-trail .d2 { top: 10px; left: -5px; animation: arc2 400ms ease-out 50ms forwards; }
.rd-magic-trail .d3 { top: 30px; left: 10px; animation: arc3 400ms ease-out 100ms forwards; }
.rd-magic-sparkle { position: absolute; font-size: 14px; opacity: 0; top: 20px; left: 30px; animation: burst 400ms ease-out 150ms forwards; }

@keyframes arc1 { 0% { opacity: 1; transform: translate(0,0) scale(1); } 100% { opacity: 0; transform: translate(15px, 15px) scale(0); } }
@keyframes arc2 { 0% { opacity: 1; transform: translate(0,0) scale(1); } 100% { opacity: 0; transform: translate(25px, 0) scale(0); } }
@keyframes arc3 { 0% { opacity: 1; transform: translate(0,0) scale(1); } 100% { opacity: 0; transform: translate(15px, -15px) scale(0); } }
@keyframes burst { 0% { opacity: 1; transform: scale(0.5) rotate(0deg); } 100% { opacity: 0; transform: scale(1.5) rotate(45deg); } }

/* Dropdown Panel */
.rd-dropdown-enter-active, .rd-dropdown-leave-active { transition: opacity 250ms, transform 250ms cubic-bezier(.34,1.56,.64,1); transform-origin: top left; }
.rd-dropdown-enter-from, .rd-dropdown-leave-to { opacity: 0; transform: scale(0.95) translateY(-10px); }

.rd-dropdown-panel {
    position: absolute; top: calc(100% + 12px); left: 0;
    width: 240px; background: #fffcf5;
    border-radius: 18px; box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 2px 10px rgba(0,0,0,0.05);
    display: flex; flex-direction: column; overflow: hidden; z-index: 50;
    border: 1px solid #fde68a55;
}
.rd-dropdown-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem 0.8rem; border-bottom: 1px solid #fde68a33; }
.rd-dropdown-title { font-size: 0.95rem; font-weight: 900; color: #1e293b; }
.rd-dropdown-close { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 8px; color: #64748b; transition: background 140ms; cursor: pointer; border: none; background: transparent; }
.rd-dropdown-close:hover { background: #f1f5f9; color: #1e293b; }
.rd-dropdown-nav { display: flex; flex-direction: column; gap: 3px; padding: 0.6rem; }
.rd-nav { display: flex; align-items: center; gap: 0.7rem; padding: 0.6rem 0.9rem; border-radius: 12px; font-size: 0.88rem; font-weight: 700; color: #64748b; text-decoration: none; transition: background 130ms, color 130ms; }
.rd-nav:hover { background: #f5f0e8; color: #1e293b; }
.rd-nav--active { background: #fff0e6; color: #F58549; }
.rd-dropdown-footer { margin: 0.5rem; padding: 0.8rem; background: linear-gradient(130deg, #fff8f0, #fef3e2); border-radius: 14px; border: 1px solid #fde68a44; display: flex; align-items: center; gap: 0.6rem; }
.rd-dropdown-footer-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 10px; background: #F58549; color: white; box-shadow: 0 4px 10px rgba(245,133,73,0.3); flex-shrink: 0; }
.rd-dropdown-footer p { font-size: 0.7rem; font-weight: 900; color: #78350f; line-height: 1.3; margin: 0; }

@media (max-width: 768px) {
    .rd-dropdown-panel { position: fixed; top: 80px; right: 1.25rem; left: 1.25rem; width: auto; max-width: 400px; margin: 0 auto; }
}

/* ════════════════════════════════════════════════
   PAGE LAYOUT
   ════════════════════════════════════════════════ */
.rd-page {
    position: relative; z-index: 5; display: grid;
    grid-template-columns: 1fr 272px; gap: 1.25rem;
    max-width: 1200px; margin: 0 auto; padding: 2.5rem 1.25rem; /* Increased top padding to accommodate absolute top-right controls */
}
@media (max-width: 960px) { .rd-page { grid-template-columns: 1fr; padding-top: 5rem; } }

.rd-left { display: flex; flex-direction: column; gap: 0.85rem; }

/* Greeting */
.rd-greeting { display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: rgba(255,255,255,0.85); backdrop-filter: blur(12px); border-radius: 18px; padding: 0.9rem 1.2rem; border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 3px 16px rgba(0,0,0,0.07); opacity: 0; transform: translateY(-10px); }
.is-loaded .rd-greeting { opacity: 1; transform: translateY(0); transition: opacity 0.5s, transform 0.5s; }
.rd-greeting-title { font-size: clamp(1.25rem, 2.5vw, 1.75rem); font-weight: 900; color: #1e293b; line-height: 1.1; }
.rd-greeting-sub { font-size: 0.85rem; font-weight: 600; color: #64748b; margin-top: 0.15rem; }
.rd-primary-btn { flex-shrink: 0; display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, #F58549, #f97316); color: white; border-radius: 14px; font-size: 0.85rem; font-weight: 900; text-decoration: none; box-shadow: 0 4px 14px rgba(245,133,73,0.38), 0 2px 0 rgba(0,0,0,0.08); transition: transform 140ms, box-shadow 140ms; white-space: nowrap; }
.rd-primary-btn:hover { transform: translateY(-2px); box-shadow: 0 7px 20px rgba(245,133,73,0.45); }

/* Status strip */
.rd-status-strip { display: flex; align-items: center; gap: 0; background: rgba(255,255,255,0.88); backdrop-filter: blur(12px); border-radius: 16px; padding: 0.65rem 1rem; border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 3px 14px rgba(0,0,0,0.06); overflow-x: auto; opacity: 0; transform: translateY(10px); }
.is-loaded .rd-status-strip { opacity: 1; transform: translateY(0); transition: opacity 0.5s 0.1s, transform 0.5s 0.1s; }
.rd-stat { display: flex; align-items: center; gap: 0.55rem; flex: 1; min-width: 0; padding: 0 0.5rem; }
.rd-stat-div { width: 1px; height: 36px; flex-shrink: 0; background: rgba(0,0,0,0.09); margin: 0 0.25rem; }
.rd-stat-emoji { font-size: 1.4rem; line-height: 1; flex-shrink: 0; }
.rd-stat-lbl { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; line-height: 1; }
.rd-stat-val { font-size: 1rem; font-weight: 900; color: #1e293b; margin-top: 0.08rem; line-height: 1.1; }
.rd-stat-val--sm { font-size: 0.78rem; }
.rd-stat-hint { font-size: 0.6rem; font-weight: 600; color: #94a3b8; }
@media (max-width: 680px) { .rd-status-strip { flex-wrap: wrap; gap: 0.5rem; } .rd-stat-div { display: none; } .rd-stat { flex: 0 0 calc(50% - 0.25rem); padding: 0.4rem 0.5rem; background: rgba(255,255,255,0.6); border-radius: 12px; } }

/* ════════════════════════════════════════════════
   MAP AREA & ANIMATIONS
   ════════════════════════════════════════════════ */
.rd-map { position: relative; display: flex; gap: 1rem; align-items: flex-start; min-height: 480px; }
.rd-mascot { position: absolute; left: 0; top: 0; transition: top 500ms cubic-bezier(.34, 1.56, .64, 1); display: flex; flex-direction: column; align-items: center; width: clamp(130px, 16vw, 190px); z-index: 3; pointer-events: none; }
.rd-mascot-img { width: 100%; max-height: 240px; object-fit: contain; filter: drop-shadow(0 6px 14px rgba(0,0,0,0.14)); }
.rd-speech-bubble { margin-top: 0.4rem; background: white; border-radius: 14px; padding: 0.5rem 0.75rem; font-size: 0.7rem; font-weight: 700; color: #374151; text-align: center; line-height: 1.4; box-shadow: 0 3px 12px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.06); position: relative; width: 100%; animation: float-bubble 3s ease-in-out infinite; }
.rd-speech-bubble::before { content: ''; position: absolute; top: -7px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 7px solid transparent; border-right: 7px solid transparent; border-bottom: 7px solid white; }
@keyframes float-bubble { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }

.rd-path { margin-left: clamp(140px, 17vw, 200px); flex: 1; display: flex; flex-direction: column; align-items: center; padding: 0.5rem 0 1.5rem; gap: 0; }
.rd-trail { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 3px 0; }
.rd-dot { display: block; width: 9px; height: 9px; border-radius: 50%; background: #d1d5db; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); transition: background 300ms, box-shadow 300ms; }
.rd-dot--completed { background: #fcd34d; box-shadow: 0 1px 4px rgba(245,158,11,0.4); animation: shimmer-dot 2s infinite ease-in-out; animation-delay: calc(var(--dot-idx) * 0.15s); }
@keyframes shimmer-dot { 0%, 100% { opacity: 0.6; transform: scale(1); } 50% { opacity: 1; transform: scale(1.15); background: #f59e0b; } }

.rd-node-row { display: flex; align-items: center; gap: 0.9rem; width: 100%; opacity: 0; transform: translateY(20px); }
.is-loaded .rd-node-row { opacity: 1; transform: translateY(0); }
.is-loaded .anim-delay-0 { transition: opacity 0.4s 0.1s, transform 0.4s 0.1s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-delay-1 { transition: opacity 0.4s 0.2s, transform 0.4s 0.2s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-delay-2 { transition: opacity 0.4s 0.3s, transform 0.4s 0.3s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-delay-3 { transition: opacity 0.4s 0.4s, transform 0.4s 0.4s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-delay-4 { transition: opacity 0.4s 0.5s, transform 0.4s 0.5s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-delay-5 { transition: opacity 0.4s 0.6s, transform 0.4s 0.6s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-delay-6 { transition: opacity 0.4s 0.7s, transform 0.4s 0.7s cubic-bezier(.34,1.56,.64,1); }
.rd-node-row--r { flex-direction: row; justify-content: flex-start; padding-left: 8%; }
.rd-node-row--l { flex-direction: row-reverse; justify-content: flex-start; padding-right: 8%; }

.rd-label { position: relative; background: #FFFDF7; border-radius: 14px; padding: 0.55rem 0.85rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 2px solid transparent; max-width: 200px; min-width: 150px; transition: box-shadow 200ms, transform 200ms, border-color 200ms; }
.rd-label-content { position: relative; z-index: 2; }
.rd-label--current { border-color: #F58549; box-shadow: 0 4px 16px rgba(245,133,73,0.25); }
.rd-label--completed { border-color: #6ee7b7; }
.rd-label--locked { opacity: 0.7; filter: grayscale(40%); }
.rd-label-title { font-size: 0.84rem; font-weight: 900; color: #1e293b; line-height: 1.2; }
.rd-label-sub { font-size: 0.68rem; font-weight: 600; color: #64748b; margin-top: 0.12rem; line-height: 1.3; }
.rd-tooltip { position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%) scale(0.9); background: #1e293b; color: white; font-size: 0.65rem; font-weight: 700; padding: 0.4rem 0.6rem; border-radius: 6px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 200ms, transform 200ms; z-index: 10; }
.rd-tooltip::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border-width: 4px; border-style: solid; border-color: #1e293b transparent transparent transparent; }
.rd-label--locked:hover .rd-tooltip { opacity: 1; transform: translateX(-50%) scale(1); }

.rd-node { position: relative; flex-shrink: 0; width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(145deg, #fbbf24, #f59e0b); box-shadow: 0 6px 0 #b45309, 0 8px 18px rgba(245,158,11,0.4); display: grid; place-items: center; cursor: default; transition: transform 170ms cubic-bezier(.34,1.56,.64,1), box-shadow 170ms ease; text-decoration: none; }
.rd-node::after { content: ''; position: absolute; inset: 5px; border-radius: 50%; background: rgba(255,255,255,0.22); pointer-events: none; }
.rd-node-face { position: relative; z-index: 2; display: grid; place-items: center; }
.rd-node-emoji { font-size: 1.65rem; line-height: 1; }
.rd-check-icon { color: white; stroke-width: 3; }

.rd-node--available { cursor: pointer; }
.rd-node--available:hover { transform: translateY(-5px); box-shadow: 0 11px 0 #b45309, 0 16px 28px rgba(245,158,11,0.5); }
.rd-node--available:hover + .rd-label, .rd-label:has(+ .rd-node--available:hover) { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }

.rd-node--current { background: linear-gradient(145deg, #fb923c, #F58549); animation: float-node 3s ease-in-out infinite; }
@keyframes float-node { 0%, 100% { transform: translateY(0); box-shadow: 0 6px 0 #c2410c, 0 8px 22px rgba(245,133,73,0.5), 0 0 0 0 rgba(245,133,73,0.4); } 50% { transform: translateY(-4px); box-shadow: 0 10px 0 #c2410c, 0 14px 34px rgba(245,133,73,0.6), 0 0 0 8px rgba(245,133,73,0); } }

.rd-node-sparkles { position: absolute; inset: -20px; pointer-events: none; z-index: 0; }
.rd-node-sparkles span { position: absolute; font-size: 12px; opacity: 0; animation: sparkle-pop 2.5s infinite; }
.rd-node-sparkles .s1 { top: 0; left: 10px; animation-delay: 0s; } .rd-node-sparkles .s2 { bottom: 0; right: 0; font-size: 16px; animation-delay: 0.8s; } .rd-node-sparkles .s3 { top: 30px; right: -10px; font-size: 10px; animation-delay: 1.6s; }
@keyframes sparkle-pop { 0% { opacity: 0; transform: scale(0) rotate(0deg); } 20% { opacity: 1; transform: scale(1.2) rotate(45deg); } 40% { opacity: 0; transform: scale(0) rotate(90deg); } 100% { opacity: 0; } }

.rd-node--completed { background: linear-gradient(145deg, #6ee7b7, #10b981); box-shadow: 0 6px 0 #059669, 0 8px 18px rgba(16,185,129,0.38); }
.is-loaded .rd-node--completed .rd-check-icon { animation: check-bounce 0.6s cubic-bezier(.34,1.56,.64,1) backwards; animation-delay: 0.4s; }
@keyframes check-bounce { 0% { transform: scale(0); } 100% { transform: scale(1); } }

.rd-star-burst { position: absolute; inset: 0; z-index: 1; pointer-events: none; }
.rd-star-burst span { position: absolute; top: 50%; left: 50%; width: 4px; height: 16px; background: #fcd34d; border-radius: 4px; opacity: 0; transform-origin: bottom center; }
.is-loaded .rd-star-burst span { animation: burst-shoot 0.6s ease-out forwards; animation-delay: 0.4s; }
.rd-star-burst .burst1 { transform: translate(-50%, -100%) rotate(0deg); } .rd-star-burst .burst2 { transform: translate(-50%, -100%) rotate(90deg); } .rd-star-burst .burst3 { transform: translate(-50%, -100%) rotate(180deg); } .rd-star-burst .burst4 { transform: translate(-50%, -100%) rotate(270deg); }
@keyframes burst-shoot { 0% { opacity: 1; transform: translate(-50%, -10px) rotate(var(--rot, 0deg)) scaleY(0.5); } 100% { opacity: 0; transform: translate(-50%, -35px) rotate(var(--rot, 0deg)) scaleY(1.5); } }
.burst1 { --rot: 0deg; } .burst2 { --rot: 90deg; } .burst3 { --rot: 180deg; } .burst4 { --rot: 270deg; }

.rd-node--locked { background: linear-gradient(145deg, #e5e7eb, #d1d5db); box-shadow: 0 5px 0 #9ca3af, 0 7px 14px rgba(0,0,0,0.1); opacity: 0.9; }
.rd-lock-wrap { width: 32px; height: 32px; border-radius: 50%; background: #9ca3af; display: grid; place-items: center; box-shadow: inset 0 2px 4px rgba(0,0,0,0.15); }
.rd-lock-icon { color: white; }
.rd-node--locked:hover { animation: shake-lock 0.4s ease-in-out; }
@keyframes shake-lock { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-4px) rotate(-4deg); } 75% { transform: translateX(4px) rotate(4deg); } }

@media (max-width: 768px) {
    .rd-map { flex-direction: column; align-items: center; }
    .rd-mascot { position: relative; top: 0 !important; width: clamp(120px, 38vw, 170px); margin-bottom: 1rem; pointer-events: auto; }
    .rd-path { margin-left: 0; width: 100%; }
    .rd-node-row--r { padding-left: 4%; }
    .rd-node-row--l { padding-right: 4%; }
}

/* ════════════════════════════════════════════════
   RIGHT WIDGETS
   ════════════════════════════════════════════════ */
.rd-widgets { display: flex; flex-direction: column; gap: 0.85rem; position: sticky; top: 2.5rem; align-self: start; }
.rd-widget { background: rgba(255,255,255,0.92); backdrop-filter: blur(12px); border-radius: 18px; padding: 1rem 1.1rem; border: 1px solid rgba(255,255,255,0.9); box-shadow: 0 3px 16px rgba(0,0,0,0.07); opacity: 0; transform: translateX(20px); }
.is-loaded .anim-widget-1 { opacity: 1; transform: translateX(0); transition: opacity 0.5s 0.3s, transform 0.5s 0.3s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-widget-2 { opacity: 1; transform: translateX(0); transition: opacity 0.5s 0.4s, transform 0.5s 0.4s cubic-bezier(.34,1.56,.64,1); }
.is-loaded .anim-widget-3 { opacity: 1; transform: translateX(0); transition: opacity 0.5s 0.5s, transform 0.5s 0.5s cubic-bezier(.34,1.56,.64,1); }

.rd-widget-hd { display: flex; align-items: center; gap: 0.55rem; margin-bottom: 0.8rem; }
.rd-widget-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 9px; color: white; flex-shrink: 0; box-shadow: 0 3px 8px rgba(0,0,0,0.15); }
.rd-widget-title { font-size: 0.88rem; font-weight: 900; color: #1e293b; flex: 1; }
.rd-streaks { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.7rem; }
.rd-streak { display: flex; align-items: center; gap: 0.55rem; }
.rd-streak-icon { font-size: 1.2rem; line-height: 1; }
.rd-streak-lbl { font-size: 0.67rem; font-weight: 700; color: #94a3b8; line-height: 1; }
.rd-streak-val { font-size: 0.82rem; font-weight: 900; color: #1e293b; margin-top: 0.1rem; }
.rd-streak-val span { font-weight: 600; color: #64748b; }
.rd-widget-note { display: flex; align-items: flex-start; gap: 0.35rem; font-size: 0.69rem; font-weight: 600; color: #78350f; background: #fef9c3; border-radius: 9px; padding: 0.45rem 0.55rem; line-height: 1.4; }
.rd-goal-text { font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 0.55rem; }
.rd-goal-row { display: flex; align-items: center; gap: 0.55rem; }
.rd-goal-track { flex: 1; height: 9px; background: #e5e7eb; border-radius: 9999px; overflow: hidden; }
.rd-goal-fill { height: 100%; background: linear-gradient(90deg, #10b981, #6ee7b7); border-radius: 9999px; transition: width 400ms ease; }
.rd-goal-cnt { font-size: 0.72rem; font-weight: 800; color: #374151; white-space: nowrap; }
.rd-rewards-sub { font-size: 0.72rem; font-weight: 600; color: #6b7280; margin-bottom: 0.65rem; }
.rd-star-row { display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.8rem; }
.rd-star-icon { color: #fbbf24; fill: #fbbf24; }
.rd-star-count { font-size: 2rem; font-weight: 900; color: #1e293b; line-height: 1; }
.rd-view-rewards { display: block; text-align: center; padding: 0.6rem; border-radius: 12px; background: white; color: #F58549; font-size: 0.82rem; font-weight: 900; border: 2px solid #F58549; text-decoration: none; transition: background 140ms, color 140ms; }
.rd-view-rewards:hover { background: #F58549; color: white; }
</style>
