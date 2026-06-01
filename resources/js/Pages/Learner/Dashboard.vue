<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Home,
    BookOpen,
    Trophy,
    HelpCircle,
    ClipboardList,
    ChevronDown,
    Eye,
    BarChart3,
    Lock,
    ArrowRight,
    Sparkles,
    MessageCircle,
    Type,
    BookMarked,
    Menu,
    X,
    Hand,
    GraduationCap,
    Star,
    Rocket,
    Smile,
    Sprout,
} from 'lucide-vue-next';

const props = defineProps({
    learner:            { type: Object, default: null },
    modules:            { type: Array,  default: () => [] },
    latestAttempt:      { type: Object, default: null },
    latestFinalAttempt: { type: Object, default: null },
    flowState:          { type: Object, default: null },
});

/* ── Mobile drawer state ─────────────────────── */
const sidebarOpen = ref(false);

/* ── Module display metadata (overrides DB title to user-spec names) ── */
const moduleMeta = {
    module_1: {
        title: 'Letter and Sound Learning',
        blurb: 'Learn the sounds that letters make and how to say them.',
    },
    module_2: {
        title: 'Word Recognition',
        blurb: 'Practice reading and recognizing words quickly and clearly.',
    },
    module_3: {
        title: 'Reading Comprehension',
        blurb: 'Read short passages and answer questions about them.',
    },
};
const metaFor = (key) => moduleMeta[key] ?? { title: 'Module', blurb: '' };

/* ── Identity & state ────────────────────────── */
const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const initial   = computed(() => firstName.value.charAt(0).toUpperCase());

const currentStage = computed(() => props.flowState?.stage ?? props.learner?.current_stage ?? 'new');
const isDone = computed(() => props.flowState?.diagnostic?.is_completed === true);
const primaryActionLabel = computed(() => props.flowState?.primary_action_label ?? 'Start Diagnostic');
const primaryActionRoute = computed(() => props.flowState?.primary_action_route ?? '/learner/diagnostic/start');
const primaryMessage = computed(() => props.flowState?.message ?? 'Begin with your diagnostic reading check.');

const assignedKey = computed(() => props.learner?.current_module?.key ?? null);
const assignedTitle = computed(() => {
    if (assignedKey.value) return metaFor(assignedKey.value).title;
    if (currentStage.value === 'grade_ready') return 'No module needed';
    if (currentStage.value?.startsWith('final_reassessment')) return 'Final reassessment';
    return props.learner?.current_module?.title ?? 'Take the diagnostic';
});

/* ── Score helpers (each task is /10) ─────────── */
const lettersScore   = computed(() => Number(props.latestAttempt?.task_1_score ?? 0));
const sentencesScore = computed(() => Number(
    props.latestAttempt?.task_2b_score ?? props.latestAttempt?.task_2a_score ?? 0
));
const passageScore   = computed(() => {
    const acc = props.latestAttempt?.reading_accuracy;
    if (acc == null) return 0;
    const num = Number(acc);
    return Math.round((num <= 1 ? num : num / 100) * 10);
});

const overallScore = computed(() => {
    if (!isDone.value) return 0;
    const total = lettersScore.value + sentencesScore.value + passageScore.value;
    return Math.round((total / 30) * 100);
});
const overallLabel = computed(() => {
    if (!isDone.value)        return 'Get started!';
    if (overallScore.value >= 70) return 'Great Job!';
    if (overallScore.value >= 40) return 'Good Job!';
    return 'Keep Practicing!';
});

const scoreStatus = (score) => {
    const pct = (score / 10) * 100;
    return pct >= 70 ? 'Good' : 'Keep Practicing';
};

/* ── Pre-computed result cards ─────────────────── */
const results = computed(() => [
    {
        title:    'Letters',
        blurb:    'Identifying letter names and sounds.',
        score:    lettersScore.value,
        icon:     Type,
        iconBg:   'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/20',
        titleClr: 'text-slate-800',
        scoreClr: 'text-slate-800',
        track:    'bg-slate-200/60',
        fill:     'bg-gradient-to-r from-emerald-400 to-emerald-500',
        pillBg:   'bg-emerald-50 border border-emerald-200/60',
        pillTxt:  'text-emerald-700',
    },
    {
        title:    'Sentences',
        blurb:    'Reading and understanding simple sentences.',
        score:    sentencesScore.value,
        icon:     MessageCircle,
        iconBg:   'bg-gradient-to-br from-orange-400 to-orange-500 shadow-orange-500/20',
        titleClr: 'text-slate-800',
        scoreClr: 'text-slate-800',
        track:    'bg-slate-200/60',
        fill:     'bg-gradient-to-r from-orange-400 to-orange-500',
        pillBg:   'bg-orange-50 border border-orange-200/60',
        pillTxt:  'text-orange-700',
    },
    {
        title:    'Reading Passage',
        blurb:    'Reading short passages and answering questions.',
        score:    passageScore.value,
        icon:     BookMarked,
        iconBg:   'bg-gradient-to-br from-violet-400 to-violet-600 shadow-violet-500/20',
        titleClr: 'text-slate-800',
        scoreClr: 'text-slate-800',
        track:    'bg-slate-200/60',
        fill:     'bg-gradient-to-r from-violet-400 to-violet-500',
        pillBg:   'bg-violet-50 border border-violet-200/60',
        pillTxt:  'text-violet-700',
    },
]);

/* ── Module card data ──────────────────────────── */
const moduleCards = computed(() =>
    props.modules.map((m) => {
        const meta = metaFor(m.key);
        const assigned = m.key === assignedKey.value && !['grade_ready', 'final_reassessment_pending', 'final_reassessment_in_progress', 'final_reassessment_completed', 'completed'].includes(currentStage.value);
        return {
            key:      m.key,
            sequence: m.sequence,
            title:    meta.title,
            blurb:    meta.blurb,
            assigned,
            locked:   !assigned,
        };
    })
);

const lockedModuleMessage = computed(() => {
    if (currentStage.value === 'new' || currentStage.value === 'diagnostic_in_progress') return 'Finish the diagnostic first';
    if (currentStage.value === 'grade_ready') return 'No module needed right now';
    if (currentStage.value?.startsWith('final_reassessment')) return 'Final reassessment is next';
    if (currentStage.value === 'completed') return 'Journey complete';
    return 'Complete your current module first';
});
</script>

<template>
    <!-- ═════════════════════════════════════════════════
         Responsive learner dashboard.
         • Mobile (<lg)  → may scroll, sidebar is a drawer
         • Desktop (lg+) → fixed viewport, no scroll
         ═════════════════════════════════════════════════ -->
    <div class="flex min-h-screen flex-col bg-slate-50 font-sans text-text lg:flex-row">

        <!-- ════════ Mobile overlay (when drawer open) ════════ -->
        <Transition name="overlay">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- ════════ SIDEBAR ════════ -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-300 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:w-55 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
        >
            <!-- Brand -->
            <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20">
                        <BookOpen :size="18" />
                    </div>
                    <span class="text-lg font-black tracking-tight text-slate-800">ReaDirect</span>
                </div>
                <button
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-800 lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <X :size="18" />
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 space-y-1.5 overflow-y-auto px-4 pt-4">
                <a
                    href="/learner/dashboard"
                    class="flex items-center gap-3 rounded-[16px] bg-gradient-to-r from-sky-50 to-blue-50/50 px-4 py-3 text-sm font-black text-blue-600 ring-1 ring-blue-100/50 transition-all"
                    @click="sidebarOpen = false"
                >
                    <Home :size="18" />
                    <span>Dashboard</span>
                </a>
                <a
                    href="/learner/modules"
                    class="flex items-center gap-3 rounded-[16px] px-4 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-800"
                    @click="sidebarOpen = false"
                >
                    <BookOpen :size="18" />
                    <span>My Learning</span>
                </a>
                <Link
                    href="/learner/progress"
                    class="flex items-center gap-3 rounded-[16px] px-4 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-800"
                    @click="sidebarOpen = false"
                >
                    <ClipboardList :size="18" />
                    <span>Progress</span>
                </Link>
                <Link
                    href="/learner/rewards"
                    class="flex items-center gap-3 rounded-[16px] px-4 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-800"
                    @click="sidebarOpen = false"
                >
                    <Trophy :size="18" />
                    <span>Rewards</span>
                </Link>
                <Link
                    href="/learner/help"
                    class="flex items-center gap-3 rounded-[16px] px-4 py-3 text-sm font-bold text-slate-500 transition-all hover:bg-slate-50 hover:text-slate-800"
                    @click="sidebarOpen = false"
                >
                    <HelpCircle :size="18" />
                    <span>Help</span>
                </Link>
            </nav>

            <!-- Mascot card at bottom -->
            <div class="m-4 rounded-[24px] border border-slate-200/80 bg-slate-50/50 p-4 text-center shadow-sm">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20">
                    <GraduationCap :size="24" />
                </div>
                <p class="mt-3 text-[12px] font-black leading-snug text-slate-600">
                    Keep learning,<br />keep growing!
                </p>
            </div>
        </aside>

        <!-- ════════ MAIN ════════ -->
        <div class="flex flex-1 flex-col">

            <!-- Top bar -->
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200/60 bg-white/80 px-4 backdrop-blur-md sm:px-6 xl:h-20 xl:px-8">
                <!-- Mobile hamburger -->
                <button
                    class="rounded-lg p-2 text-slate-600 transition-colors hover:bg-slate-100 lg:hidden"
                    aria-label="Open menu"
                    @click="sidebarOpen = true"
                >
                    <Menu :size="22" />
                </button>

                <!-- Spacer (right-align controls on desktop) -->
                <div class="hidden flex-1 lg:block" />

                <!-- Right side controls -->
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 rounded-full border border-slate-200/80 bg-white py-1 pl-1 pr-4 shadow-sm xl:py-1.5 xl:pl-1.5 xl:pr-5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-blue-600 text-sm font-black text-white shadow-sm shadow-blue-500/20 xl:h-9 xl:w-9">
                            {{ initial }}
                        </div>
                        <span class="hidden text-sm font-black text-slate-700 sm:inline xl:text-base">{{ firstName }}</span>
                        <ChevronDown :size="16" class="hidden text-slate-400 sm:block" />
                    </div>
                </div>
            </header>

            <!-- Content (scrollable, natural flow) -->
            <main class="flex flex-1 flex-col gap-3 p-3 sm:p-4 xl:gap-4">

                <!-- ──── HERO ──── -->
                <section class="relative shrink-0 overflow-hidden rounded-[36px] bg-gradient-to-br from-sky-400 to-blue-600 p-6 text-white shadow-xl shadow-blue-500/20 sm:p-8 xl:p-10">
                    <!-- Decorative blob -->
                    <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-10 right-40 h-32 w-32 rounded-full bg-white/5 blur-2xl" />

                    <div class="relative grid items-center gap-6 lg:grid-cols-[1fr_auto_auto] lg:gap-8">

                        <!-- Greeting -->
                        <div class="min-w-0">
                            <h1 class="flex flex-wrap items-center gap-3 text-3xl font-black sm:text-4xl xl:text-5xl">
                                Hi, {{ firstName }}!
                                <Hand :size="32" class="text-yellow-200" />
                            </h1>
                            <p class="mt-3 text-sm font-bold text-white/90 sm:text-base xl:text-lg">
                                {{ primaryMessage }}
                            </p>
                            <p class="mt-1 text-sm font-bold text-white/80 xl:text-base">
                                Current stage: <span class="capitalize">{{ currentStage.replaceAll('_', ' ') }}</span>
                            </p>
                            <Link
                                :href="primaryActionRoute"
                                class="mt-6 inline-flex items-center gap-3 rounded-[20px] bg-white px-6 py-3 text-base font-black text-blue-600 shadow-lg shadow-black/5 ring-1 ring-black/5 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] hover:bg-blue-50 hover:shadow-xl active:scale-[0.98] xl:px-8 xl:py-4 xl:text-lg"
                            >
                                <Sparkles :size="20" />
                                {{ primaryActionLabel }}
                                <ArrowRight :size="20" class="stroke-[3]" />
                            </Link>
                        </div>

                        <!-- Trophy (hidden on smaller screens to save space) -->
                        <div class="hidden h-20 w-20 items-center justify-center rounded-3xl bg-white/15 ring-1 ring-white/30 backdrop-blur-md xl:flex">
                            <Trophy :size="40" class="text-yellow-200" />
                        </div>

                        <!-- Stat tiles (responsive) -->
                        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:flex lg:flex-nowrap">
                            <div class="rounded-[24px] bg-white/15 p-4 ring-1 ring-white/30 backdrop-blur-md lg:min-w-36 xl:p-5">
                                <p class="text-[10px] font-black uppercase tracking-widest text-white/80 xl:text-[11px]">Overall Score</p>
                                <p class="mt-2 text-3xl font-black leading-none xl:text-4xl">{{ overallScore }}%</p>
                                <p class="mt-2 text-[12px] font-bold text-white/90">{{ overallLabel }}</p>
                            </div>
                            <div class="rounded-[24px] bg-white/15 p-4 ring-1 ring-white/30 backdrop-blur-md lg:min-w-48 xl:p-5">
                                <p class="text-[10px] font-black uppercase tracking-widest text-white/80 xl:text-[11px]">Assigned Module</p>
                                <p class="mt-2 truncate text-base font-black leading-tight xl:text-lg">{{ assignedTitle }}</p>
                                <p class="mt-2 text-[12px] font-bold text-white/90">Your current learning path</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ──── DIAGNOSTIC RESULTS ──── -->
                <section class="shrink-0 rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 xl:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 text-base font-black text-slate-800 sm:text-lg xl:text-xl">
                            <BarChart3 :size="24" class="text-primary" />
                            Diagnostic Results
                        </h2>
                        <Link
                            v-if="isDone"
                            :href="props.flowState?.diagnostic?.is_completed ? '/learner/diagnostic/reading-summary' : primaryActionRoute"
                            class="flex items-center gap-1.5 rounded-full border border-blue-200/60 bg-blue-50/50 px-4 py-2 text-sm font-bold text-primary transition-all hover:bg-blue-100/50"
                        >
                            <Eye :size="16" />
                            <span>View Full Result</span>
                        </Link>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4 xl:gap-5">
                        <article
                            v-for="r in results"
                            :key="r.title"
                            class="rounded-[28px] border border-slate-200/60 bg-slate-50/50 p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md xl:p-6"
                        >
                            <div class="flex items-start gap-3 xl:gap-4">
                                <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-md ring-1 ring-white/20 xl:h-14 xl:w-14', r.iconBg]">
                                    <component :is="r.icon" :size="22" />
                                </div>
                                <div class="min-w-0">
                                    <h3 :class="['text-base font-black leading-tight xl:text-lg', r.titleClr]">{{ r.title }}</h3>
                                    <p class="mt-1 line-clamp-2 text-xs font-semibold leading-snug text-slate-500 xl:text-sm">{{ r.blurb }}</p>
                                </div>
                            </div>

                            <p class="mt-4 xl:mt-5">
                                <span :class="['text-3xl font-black xl:text-4xl', r.scoreClr]">{{ r.score }}</span>
                                <span class="text-sm font-bold text-slate-400 xl:text-base"> / 10</span>
                            </p>

                            <div :class="['mt-3 h-2.5 w-full overflow-hidden rounded-full shadow-inner xl:mt-4', r.track]">
                                <div
                                    :class="['h-full rounded-full transition-all duration-500', r.fill]"
                                    :style="{ width: `${(r.score / 10) * 100}%` }"
                                />
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-2 xl:mt-4">
                                <span :class="['text-sm font-bold xl:text-base', r.scoreClr]">{{ Math.round((r.score / 10) * 100) }}%</span>
                                <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-wider xl:text-[12px]', r.pillBg, r.pillTxt]">
                                    <Smile v-if="scoreStatus(r.score) === 'Good'" :size="14" />
                                    <Sprout v-else :size="14" />
                                    {{ scoreStatus(r.score) }}
                                </span>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- ──── LEARNING MODULES ──── -->
                <section class="shrink-0 rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 xl:p-8">
                    <div>
                        <h2 class="flex items-center gap-2 text-base font-black text-slate-800 sm:text-lg xl:text-xl">
                            <BookOpen :size="24" class="text-primary" />
                            My Learning Modules
                            <Sparkles :size="18" class="text-yellow-400" />
                        </h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500 sm:text-sm xl:text-base">
                            Focus on your current module and have fun learning!
                        </p>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3 xl:gap-5">

                        <template v-for="m in moduleCards" :key="m.key">

                            <!-- Assigned (clickable) -->
                            <Link
                                v-if="m.assigned"
                                :href="`/learner/modules/${m.key}/start`"
                                class="group relative flex flex-col overflow-hidden rounded-[28px] border-2 border-primary bg-white p-5 text-left shadow-lg shadow-primary/10 transition-all duration-200 hover:-translate-y-1 hover:shadow-xl xl:p-6"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-lg font-black text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20 xl:h-14 xl:w-14 xl:text-xl">
                                        {{ m.sequence ?? '?' }}
                                    </div>
                                    <span class="rounded-full bg-blue-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-primary xl:text-[11px]">
                                        Current Module
                                    </span>
                                </div>

                                <h3 class="mt-4 text-base font-black leading-tight text-slate-800 xl:text-lg">{{ m.title }}</h3>
                                <p class="mt-1.5 line-clamp-2 text-xs font-semibold leading-snug text-slate-500 xl:text-sm">{{ m.blurb }}</p>

                                <div class="flex-1 min-h-[1.5rem]" />

                                <div class="mt-4 flex items-center justify-between gap-2 rounded-xl bg-gradient-to-br from-primary to-blue-600 px-4 py-3 text-sm font-black text-white shadow-md shadow-blue-500/20 transition-all group-hover:scale-[1.02] xl:px-5 xl:py-3.5 xl:text-base">
                                    <span>Continue Learning</span>
                                    <ArrowRight :size="18" class="stroke-[3] transition-transform group-hover:translate-x-1" />
                                </div>
                            </Link>

                            <!-- Locked -->
                            <article
                                v-else
                                aria-disabled="true"
                                class="flex cursor-not-allowed flex-col rounded-[28px] border border-slate-200/60 bg-slate-50/50 p-5 opacity-70 xl:p-6"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-200/80 text-lg font-black text-slate-500 shadow-sm xl:h-14 xl:w-14 xl:text-xl">
                                        {{ m.sequence ?? '?' }}
                                    </div>
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <Lock :size="18" />
                                    </div>
                                </div>

                                <h3 class="mt-4 text-base font-black leading-tight text-slate-500 xl:text-lg">{{ m.title }}</h3>
                                <p class="mt-1.5 line-clamp-2 text-xs font-semibold leading-snug text-slate-400 xl:text-sm">{{ m.blurb }}</p>

                                <div class="flex-1 min-h-[1.5rem]" />

                                <div class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-slate-200/80 bg-slate-100 px-4 py-3 text-xs font-bold text-slate-500 shadow-sm xl:px-5 xl:py-3.5 xl:text-sm">
                                    <Lock :size="14" />
                                    <span>{{ lockedModuleMessage }}</span>
                                </div>
                            </article>
                        </template>
                    </div>
                    <div v-if="['grade_ready', 'final_reassessment_pending', 'final_reassessment_in_progress', 'final_reassessment_completed', 'completed', 'extra_phoneme_drills'].includes(currentStage)" class="mt-5 rounded-[24px] border border-blue-200/60 bg-blue-50/50 px-6 py-4 text-base font-bold text-primary">
                        {{ primaryMessage }}
                    </div>
                </section>

                <!-- ──── BOTTOM ENCOURAGEMENT BAR ──── -->
                <footer class="flex shrink-0 flex-col items-stretch justify-between gap-3 rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/30 sm:flex-row sm:items-center sm:gap-4 xl:p-6">
                    <p class="flex items-center gap-3 text-sm font-bold text-slate-700 xl:text-base">
                        <Star :size="24" class="shrink-0 fill-yellow-400 text-yellow-400" />
                        <span class="min-w-0">You're doing amazing! Keep going and enjoy your learning adventure!</span>
                        <Rocket :size="20" class="hidden shrink-0 text-primary sm:block" />
                    </p>
                    <Link href="/learner/help" class="flex shrink-0 items-center justify-center gap-2 rounded-[20px] bg-primary px-6 py-3 text-base font-black text-white shadow-lg shadow-primary/30 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] hover:bg-primary-dark xl:text-lg">
                        <HelpCircle :size="18" />
                        Need Help?
                    </Link>
                </footer>
            </main>
        </div>
    </div>
</template>
