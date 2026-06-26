<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Star, Rocket, CheckCircle2, XCircle } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({
    attempt: Object,
    placementPreview: Object,
    taskTwoBReview: Object,
    passageEligible: Boolean,
});

const score = computed(() => props.attempt?.crla_total_score ?? 0);
const total = 30;

// Score ring math
const radius = 40;
const circumference = 2 * Math.PI * radius;
const animatedDash = ref(`0 ${circumference.toFixed(1)}`);

onMounted(() => {
    requestAnimationFrame(() => {
        setTimeout(() => {
            const pct = score.value / total;
            animatedDash.value = `${(pct * circumference).toFixed(1)} ${circumference.toFixed(1)}`;
        }, 300);
    });
});

const scoreColor = computed(() => {
    const pct = score.value / total;
    if (pct >= 0.8) return { ring: 'url(#ringGrad)', hex: '#22C55E', glow: 'rgba(34,197,94,0.25)', text: '#16A34A' };
    if (pct >= 0.5) return { ring: 'url(#ringGrad)', hex: '#3B82F6', glow: 'rgba(59,130,246,0.25)', text: '#2563EB' };
    if (pct >= 0.3) return { ring: 'url(#ringGrad)', hex: '#F59E0B', glow: 'rgba(245,158,11,0.25)', text: '#D97706' };
    return { ring: 'url(#ringGrad)', hex: '#EF4444', glow: 'rgba(239,68,68,0.25)', text: '#DC2626' };
});

const gradStart = computed(() => {
    const pct = score.value / total;
    if (pct >= 0.8) return ['#6EE7B7', '#10B981'];
    if (pct >= 0.5) return ['#93C5FD', '#3B82F6'];
    if (pct >= 0.3) return ['#FDE68A', '#F59E0B'];
    return ['#FCA5A5', '#EF4444'];
});

const agentMessage = computed(() => {
    if (props.passageEligible) {
        return `Great job completing the tasks! We will review your scores, then read a short passage together.`;
    }
    return `Excellent work! You have finished all the assessment tasks. Let's look at your final score.`;
});

const nextHref = computed(() => props.passageEligible ? '/learner/diagnostic/reading-intro' : '/learner/diagnostic/module-placement');
const nextLabel = computed(() => props.passageEligible ? 'Continue to Passage' : 'View Module Placement');

const tasks = computed(() => [
    { name: 'Task 1: Letters',    score: props.attempt?.task_1_score,  outOf: 10, passed: (props.attempt?.task_1_score ?? 0) >= 7 },
    { name: 'Task 2A: Rhymes',    score: props.attempt?.task_2a_score, outOf: 10, passed: (props.attempt?.task_2a_score ?? 0) >= 7 },
    { name: 'Task 2B: Sentences', score: props.attempt?.task_2b_score, outOf: 10, passed: (props.attempt?.task_2b_score ?? 0) >= 7 },
]);

// Confetti
const confetti = ref([]);
onMounted(() => {
    const colors = ['#6EE7B7','#93C5FD','#FDE68A','#FDA4AF','#C4B5FD','#6EE7B7','#FCD34D'];
    confetti.value = Array.from({ length: 30 }, (_, i) => ({
        id: i,
        color: colors[i % colors.length],
        left: Math.random() * 100,
        delay: Math.random() * 1.8,
        dur: 1.8 + Math.random() * 1.2,
        size: 6 + Math.random() * 8,
        rotate: Math.random() * 360,
    }));
});
</script>

<template>
    <LearnerLayout :progress="65" diagnostic-step="task-2b">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="encouraging"
                :message="agentMessage"
                compact
            />
        </template>

        <div class="cs-page">

            <!-- ── Confetti ──────────────────────────────────── -->
            <div class="cs-confetti-wrap" aria-hidden="true">
                <span
                    v-for="p in confetti"
                    :key="p.id"
                    class="cs-confetti-piece"
                    :style="{
                        left: p.left + '%',
                        width: p.size + 'px',
                        height: p.size + 'px',
                        background: p.color,
                        animationDuration: p.dur + 's',
                        animationDelay: p.delay + 's',
                        transform: `rotate(${p.rotate}deg)`,
                    }"
                />
            </div>

            <!-- ── Trophy + Title Banner ────────────────────── -->
            <div class="cs-banner anim-in" style="--d:0ms">
                <div class="cs-trophy-wrap">
                    <span class="cs-trophy">🏆</span>
                    <span class="cs-trophy-glow" />
                </div>
                <div class="cs-badge">
                    <Star class="cs-badge-star" />
                    CRLA Complete!
                </div>
                <h1 class="cs-title">Core Reading Assessment</h1>
                <p class="cs-subtitle">You scored <strong>{{ score }}</strong> out of <strong>{{ total }}</strong> points across 3 tasks.</p>
            </div>

            <!-- ── Score Ring Card ──────────────────────────── -->
            <div class="cs-score-card anim-in" style="--d:120ms">
                <div class="cs-ring-wrap" :style="{ '--glow': scoreColor.glow }">
                    <svg class="cs-ring-svg" viewBox="0 0 100 100" fill="none">
                        <defs>
                            <linearGradient id="ringGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" :stop-color="gradStart[0]" />
                                <stop offset="100%" :stop-color="gradStart[1]" />
                            </linearGradient>
                        </defs>
                        <!-- Track -->
                        <circle cx="50" cy="50" :r="radius" stroke="#E2E8F0" stroke-width="9" stroke-linecap="round"/>
                        <!-- Score arc -->
                        <circle
                            cx="50" cy="50" :r="radius"
                            :stroke="scoreColor.ring"
                            stroke-width="9"
                            stroke-linecap="round"
                            :stroke-dasharray="animatedDash"
                            stroke-dashoffset="0"
                            transform="rotate(-90 50 50)"
                            class="cs-ring-arc"
                        />
                    </svg>
                    <div class="cs-ring-inner">
                        <span class="cs-score-num" :style="{ color: scoreColor.text }">{{ score }}</span>
                        <span class="cs-score-denom">/{{ total }}</span>
                    </div>
                </div>

                <!-- Task mini bars -->
                <div class="cs-task-list">
                    <div
                        v-for="(task, idx) in tasks"
                        :key="idx"
                        class="cs-task-row"
                        :style="{ animationDelay: (200 + idx * 80) + 'ms' }"
                    >
                        <div class="cs-task-left">
                            <component
                                :is="task.passed ? CheckCircle2 : XCircle"
                                class="cs-task-icon"
                                :class="task.passed ? 'cs-task-icon--pass' : 'cs-task-icon--fail'"
                            />
                            <span class="cs-task-name">{{ task.name }}</span>
                        </div>
                        <div class="cs-task-right">
                            <div class="cs-bar-wrap">
                                <div
                                    class="cs-bar-fill"
                                    :class="task.passed ? 'cs-bar--pass' : 'cs-bar--fail'"
                                    :style="{ width: ((task.score ?? 0) / task.outOf * 100) + '%', transitionDelay: (400 + idx * 100) + 'ms' }"
                                />
                            </div>
                            <span class="cs-task-score" :class="task.passed ? 'cs-score--pass' : 'cs-score--fail'">
                                {{ task.score }}/{{ task.outOf }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Placement Card ───────────────────────────── -->
            <div class="cs-placement-card anim-in" style="--d:280ms">
                <div class="cs-placement-icon-wrap">
                    <Rocket class="cs-placement-icon" />
                </div>
                <div class="cs-placement-body">
                    <p class="cs-placement-label">Your Placement</p>
                    <p class="cs-placement-title">{{ attempt?.crla_classification }}</p>
                    <p class="cs-placement-desc">
                        {{ placementPreview?.decision_reason || 'You have successfully completed the core reading assessment.' }}
                    </p>
                </div>
            </div>

            <!-- Floating deco stars -->
            <span class="cs-deco cs-deco--1">✦</span>
            <span class="cs-deco cs-deco--2">✦</span>
            <span class="cs-deco cs-deco--3">✦</span>
        </div>

        <BottomActionBar>
            <Link :href="nextHref">
                <PrimaryButton>
                    <span class="inline-flex items-center gap-3">
                        {{ nextLabel }}
                        <ChevronRight class="size-6 stroke-[3]" />
                    </span>
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* ── Page ───────────────────────────────────────────── */
.cs-page {
    position: relative;
    display: grid;
    gap: 1.1rem;
    max-width: 44rem;
    margin: 0 auto;
    padding: 0.5rem 0 3rem;
    overflow: hidden;
}

/* ── Confetti ───────────────────────────────────────── */
.cs-confetti-wrap {
    pointer-events: none;
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 0;
}
.cs-confetti-piece {
    position: absolute;
    top: -20px;
    border-radius: 3px;
    opacity: 0;
    animation: confettiFall linear forwards;
}
@keyframes confettiFall {
    0%   { transform: translateY(0) rotate(0deg);   opacity: 1; }
    80%  { opacity: 1; }
    100% { transform: translateY(400px) rotate(720deg); opacity: 0; }
}

/* ── Entrance animation ──────────────────────────────── */
.anim-in {
    animation: slideUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--d, 0ms);
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(22px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Banner ─────────────────────────────────────────── */
.cs-banner {
    position: relative;
    z-index: 1;
    text-align: center;
    background: linear-gradient(135deg, #EFF6FF 0%, #F0FDF4 100%);
    border: 1.5px solid rgba(191, 219, 254, 0.7);
    border-radius: 28px;
    padding: 2rem 1.5rem 1.5rem;
    box-shadow: 0 8px 32px rgba(30, 58, 138, 0.08), 0 1px 0 rgba(255,255,255,0.9) inset;
}

.cs-trophy-wrap {
    position: relative;
    display: inline-block;
    margin-bottom: 0.75rem;
}
.cs-trophy {
    font-size: clamp(3rem, 10vw, 4.5rem);
    display: block;
    filter: drop-shadow(0 4px 16px rgba(245,158,11,0.35));
    animation: trophyBounce 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.4s both;
}
@keyframes trophyBounce {
    from { opacity: 0; transform: scale(0.3) rotate(-15deg); }
    to   { opacity: 1; transform: scale(1) rotate(0deg); }
}
.cs-trophy-glow {
    position: absolute;
    inset: -30%;
    background: radial-gradient(circle, rgba(251,191,36,0.2) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.cs-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 1rem;
    border-radius: 9999px;
    background: linear-gradient(135deg, rgba(254,243,199,0.9), rgba(253,230,138,0.9));
    border: 1.5px solid rgba(251,191,36,0.5);
    font-family: 'Fredoka', system-ui, sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    color: #92400E;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 8px rgba(245,158,11,0.2);
    animation: badgePop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.6s both;
}
@keyframes badgePop {
    from { opacity: 0; transform: scale(0.7); }
    to   { opacity: 1; transform: scale(1); }
}
.cs-badge-star {
    width: 0.9rem;
    height: 0.9rem;
    fill: #F59E0B;
    stroke: #F59E0B;
}

.cs-title {
    font-family: 'Fredoka', system-ui, sans-serif;
    font-weight: 700;
    font-size: clamp(1.4rem, 4vw, 1.9rem);
    color: #1E3A8A;
    line-height: 1.15;
    margin: 0 0 0.5rem;
}
.cs-subtitle {
    font-size: 0.9rem;
    font-weight: 500;
    color: #64748B;
    line-height: 1.5;
}
.cs-subtitle strong { color: #1E3A8A; font-weight: 700; }

/* ── Score Card ─────────────────────────────────────── */
.cs-score-card {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1.75rem;
    background: white;
    border: 1.5px solid rgba(191, 219, 254, 0.7);
    border-radius: 28px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 8px 32px rgba(30, 58, 138, 0.07), 0 1px 0 rgba(255,255,255,0.9) inset;
}

/* Ring */
.cs-ring-wrap {
    position: relative;
    flex-shrink: 0;
    width: clamp(7rem, 20vw, 9.5rem);
    height: clamp(7rem, 20vw, 9.5rem);
    filter: drop-shadow(0 0 16px var(--glow, rgba(34,197,94,0.2)));
}
.cs-ring-svg {
    width: 100%;
    height: 100%;
}
.cs-ring-arc {
    transition: stroke-dasharray 1.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.cs-ring-inner {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.cs-score-num {
    font-family: 'Fredoka', system-ui, sans-serif;
    font-weight: 700;
    font-size: clamp(2rem, 6vw, 2.8rem);
}
.cs-score-denom {
    font-size: 0.8rem;
    font-weight: 700;
    color: #94A3B8;
    margin-top: 0.1rem;
}

/* Task list */
.cs-task-list {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.cs-task-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.cs-task-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}
.cs-task-icon {
    flex-shrink: 0;
    width: 1.1rem;
    height: 1.1rem;
    stroke-width: 2.5;
}
.cs-task-icon--pass { color: #22C55E; }
.cs-task-icon--fail { color: #EF4444; }
.cs-task-name {
    font-family: 'Fredoka', system-ui, sans-serif;
    font-weight: 600;
    font-size: 0.9rem;
    color: #1E293B;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cs-task-right {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-shrink: 0;
}
.cs-bar-wrap {
    width: 3.5rem;
    height: 6px;
    background: #F1F5F9;
    border-radius: 9999px;
    overflow: hidden;
}
.cs-bar-fill {
    height: 100%;
    border-radius: 9999px;
    width: 0;
    transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.cs-bar--pass { background: linear-gradient(90deg, #86EFAC, #22C55E); }
.cs-bar--fail { background: linear-gradient(90deg, #FCA5A5, #EF4444); }
.cs-task-score {
    font-family: 'Fredoka', system-ui, sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    min-width: 2.5rem;
    text-align: right;
}
.cs-score--pass { color: #16A34A; }
.cs-score--fail { color: #DC2626; }

/* ── Placement Card ─────────────────────────────────── */
.cs-placement-card {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: linear-gradient(135deg, #EFF6FF 0%, #F0F9FF 100%);
    border: 1.5px solid rgba(147, 197, 253, 0.6);
    border-radius: 24px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 6px 24px rgba(30, 58, 138, 0.07);
}
.cs-placement-icon-wrap {
    flex-shrink: 0;
    display: grid;
    place-items: center;
    width: 3rem;
    height: 3rem;
    border-radius: 14px;
    background: linear-gradient(135deg, #60A5FA, #2563EB);
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
    margin-top: 0.1rem;
}
.cs-placement-icon {
    width: 1.5rem;
    height: 1.5rem;
    stroke: white;
    stroke-width: 2.5;
}
.cs-placement-label {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #94A3B8;
    margin-bottom: 0.3rem;
}
.cs-placement-title {
    font-family: 'Fredoka', system-ui, sans-serif;
    font-weight: 700;
    font-size: 1.1rem;
    color: #1E3A8A;
    margin-bottom: 0.3rem;
}
.cs-placement-desc {
    font-size: 0.85rem;
    font-weight: 500;
    color: #475569;
    line-height: 1.5;
}

/* ── Deco stars ─────────────────────────────────────── */
.cs-deco {
    pointer-events: none;
    position: absolute;
    font-weight: 900;
    color: rgba(59, 130, 246, 0.08);
    animation: decoFloat 4s ease-in-out infinite alternate;
    z-index: 0;
}
.cs-deco--1 { right: -0.5rem; top: 2rem;   font-size: 2.5rem; animation-delay: 0s; }
.cs-deco--2 { left: -0.5rem;  bottom: 6rem; font-size: 1.8rem; animation-delay: 1.2s; }
.cs-deco--3 { right: 2rem;    bottom: 2rem; font-size: 1.2rem; animation-delay: 0.6s; }
@keyframes decoFloat {
    from { transform: translateY(0) rotate(0deg); }
    to   { transform: translateY(-8px) rotate(15deg); }
}

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 520px) {
    .cs-score-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 1.25rem;
    }
    .cs-task-row {
        justify-content: space-between;
    }
    .cs-bar-wrap { width: 3rem; }
}
</style>
