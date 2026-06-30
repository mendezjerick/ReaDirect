<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Award, BarChart3, CheckCircle2, Home, ShieldCheck, Trophy } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';
import CompletionCertificate from '../../Components/Learner/CompletionCertificate.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    resultSummary: {
        type: Object,
        default: () => ({
            cards: [],
            fallbackMessage: 'Your final reading check has been completed.',
        }),
    },
    agentMessages: { type: Array, default: () => [] },
    thankYouUrl: { type: String, default: '/learner/completion/thank-you' },
    homeUrl: { type: String, default: '/' },
});

const form = useForm({});
const confettiCanvas = ref(null);
let animFrame = null;

const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const lastName = computed(() => props.learner?.last_name ?? '');
const cards = computed(() => props.resultSummary?.cards ?? []);
const hasAnyMetrics = computed(() => cards.value.some((card) => (card.metrics ?? []).length > 0));
const finalCard = computed(() => cards.value.find((card) => card.kind === 'final') ?? {});
const progressCard = computed(() => cards.value.find((card) => card.kind === 'progress') ?? {});

const metric = (card, label) => (card.metrics ?? [])
    .find((item) => item.label?.toLowerCase() === label.toLowerCase())
    ?.value ?? '';

const certData = computed(() => ({
    readingLevel: metric(finalCard.value, 'reading level') || metric(finalCard.value, 'reading classification'),
    accuracyScore: metric(finalCard.value, 'reading accuracy'),
    crlaLevel: metric(finalCard.value, 'crla level') || metric(finalCard.value, 'crla score'),
    completedAt: props.resultSummary?.completedAt ?? '',
}));

const guideAgent = computed(() => (
    props.agentMessages.find((agent) => agent.agentType === 'evaluator')
    ?? props.agentMessages[props.agentMessages.length - 1]
    ?? null
));

const guideAgentType = computed(() => guideAgent.value?.agentType ?? 'evaluator');
const guideMessage = computed(() => {
    const agent = guideAgent.value;
    const message = [agent?.resultMessage, agent?.message].filter(Boolean).join(' ');

    return message || `Great job, ${firstName.value}. Your reading journey is complete.`;
});

const supportingAgentMessages = computed(() => props.agentMessages.filter((agent) => agent.name !== guideAgent.value?.name));

const summaryTiles = computed(() => [
    {
        label: 'Reading journey',
        value: 'Complete',
        caption: 'All required work finished',
        icon: CheckCircle2,
        tone: 'green',
    },
    {
        label: 'Final reading',
        value: certData.value.readingLevel || 'Recorded',
        caption: certData.value.accuracyScore ? `${certData.value.accuracyScore} accuracy` : 'Final check saved',
        icon: BarChart3,
        tone: 'orange',
    },
    {
        label: 'Certificate',
        value: 'Ready',
        caption: 'Displayed below',
        icon: Award,
        tone: 'gold',
    },
]);

const submitThankYou = () => {
    form.post(props.thankYouUrl, { preserveScroll: false });
};

const startConfetti = () => {
    const canvas = confettiCanvas.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const resize = () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    };

    const colors = ['#F58549', '#EEC170', '#10B981', '#365365', '#7C3AED', '#EF4444'];
    const particles = Array.from({ length: 58 }, () => {
        const particle = {};
        const reset = (initial = false) => {
            particle.x = Math.random() * canvas.width;
            particle.y = initial ? Math.random() * canvas.height - canvas.height : -20;
            particle.w = Math.random() * 8 + 4;
            particle.h = Math.random() * 5 + 3;
            particle.vy = Math.random() * 2.2 + 1.2;
            particle.vx = Math.random() * 1.8 - 0.9;
            particle.rot = Math.random() * 360;
            particle.rs = Math.random() * 4 - 2;
            particle.color = colors[Math.floor(Math.random() * colors.length)];
            particle.opacity = Math.random() * 0.35 + 0.55;
        };

        reset(true);
        particle.reset = reset;
        return particle;
    });

    const draw = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        particles.forEach((particle) => {
            particle.y += particle.vy;
            particle.x += particle.vx;
            particle.rot += particle.rs;

            if (particle.y > canvas.height + 20) {
                particle.reset();
            }

            ctx.save();
            ctx.globalAlpha = particle.opacity;
            ctx.translate(particle.x, particle.y);
            ctx.rotate((particle.rot * Math.PI) / 180);
            ctx.fillStyle = particle.color;
            ctx.fillRect(-particle.w / 2, -particle.h / 2, particle.w, particle.h);
            ctx.restore();
        });

        animFrame = requestAnimationFrame(draw);
    };

    window.addEventListener('resize', resize);
    resize();
    draw();

    return () => window.removeEventListener('resize', resize);
};

let cleanupConfetti = null;

onMounted(() => {
    cleanupConfetti = startConfetti();
});

onBeforeUnmount(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
    cleanupConfetti?.();
});
</script>

<template>
    <GuideLayout
        :progress="100"
        layout="stacked"
        max-width="76rem"
        :agent-type="guideAgentType"
        agent-state="celebrating"
        :agent-message="guideMessage"
        agent-line-key="estelle.completion.final_check_complete"
        agent-allow-congrats
        eyebrow="Reading Journey Complete"
        divider-label="Completion"
        primary-label="Finish and Submit"
        :primary-disabled="form.processing"
        @primary="submitThankYou"
    >
        <template #primary-icon>
            <Home class="size-5" />
        </template>

        <template #title>
            Great job, <span class="guide-title-accent">{{ firstName }}</span>
        </template>

        <canvas ref="confettiCanvas" class="completion-confetti" aria-hidden="true" />

        <section class="completion-shell">
            <div class="completion-hero guide-anim" style="--guide-delay: 190ms">
                <span class="completion-hero-icon">
                    <Trophy class="size-8" />
                </span>
                <div class="completion-hero-copy">
                    <p class="completion-hero-title">Assessment complete</p>
                    <p class="completion-hero-text">
                        Your final reading check has been saved and your certificate is ready.
                    </p>
                </div>
            </div>

            <div class="completion-tile-grid">
                <article
                    v-for="(tile, index) in summaryTiles"
                    :key="tile.label"
                    class="guide-progress-card guide-anim completion-tile"
                    :class="`completion-tile--${tile.tone}`"
                    :style="`--guide-delay: ${250 + index * 45}ms`"
                >
                    <span class="completion-tile-icon">
                        <component :is="tile.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="guide-kicker">{{ tile.label }}</span>
                    <span class="completion-tile-value">{{ tile.value }}</span>
                    <span class="completion-tile-caption">{{ tile.caption }}</span>
                </article>
            </div>

            <div v-if="hasAnyMetrics" class="completion-score-grid">
                <article
                    v-for="(card, index) in cards"
                    :key="card.title"
                    class="guide-progress-card guide-anim completion-score-card"
                    :class="{ 'completion-score-card--progress': card.kind === 'progress' }"
                    :style="`--guide-delay: ${390 + index * 55}ms`"
                >
                    <div class="completion-score-header">
                        <span class="completion-score-icon">
                            <CheckCircle2 class="size-4" />
                        </span>
                        <h2 class="completion-score-title">{{ card.title }}</h2>
                    </div>

                    <div class="completion-metric-list">
                        <div
                            v-for="item in card.metrics"
                            :key="item.label"
                            class="completion-metric"
                        >
                            <span class="completion-metric-label">{{ item.label }}</span>
                            <span
                                class="completion-metric-value"
                                :class="{
                                    'completion-metric-value--positive': card.kind === 'progress' && String(item.value).startsWith('+'),
                                }"
                            >
                                {{ item.value }}
                            </span>
                        </div>
                    </div>

                    <p v-if="card.message" class="completion-score-message">{{ card.message }}</p>
                </article>
            </div>

            <p v-else class="guide-status guide-status--warning guide-anim" style="--guide-delay: 390ms">
                {{ resultSummary.fallbackMessage }}
            </p>

            <section class="completion-certificate guide-anim" style="--guide-delay: 560ms">
                <div class="completion-section-heading">
                    <span class="completion-section-icon">
                        <Award class="size-5" />
                    </span>
                    <div>
                        <p class="guide-kicker">Certificate</p>
                        <h2>Your Certificate of Completion</h2>
                    </div>
                </div>
                <CompletionCertificate
                    :first-name="firstName"
                    :last-name="lastName"
                    :completed-at="certData.completedAt"
                    :reading-level="certData.readingLevel"
                    :accuracy-score="certData.accuracyScore"
                    :crla-level="certData.crlaLevel"
                />
            </section>

            <section
                v-if="supportingAgentMessages.length"
                class="completion-agent-notes guide-anim"
                style="--guide-delay: 640ms"
                aria-label="Messages from your reading guides"
            >
                <div class="completion-section-heading">
                    <span class="completion-section-icon completion-section-icon--muted">
                        <ShieldCheck class="size-5" />
                    </span>
                    <div>
                        <p class="guide-kicker">Guide notes</p>
                        <h2>Messages from your reading guides</h2>
                    </div>
                </div>

                <div class="completion-note-grid">
                    <article
                        v-for="agent in supportingAgentMessages"
                        :key="agent.name"
                        class="completion-note"
                    >
                        <p class="completion-note-name">{{ agent.name }}</p>
                        <p class="completion-note-role">{{ agent.role }}</p>
                        <p class="completion-note-text">{{ agent.message }}</p>
                    </article>
                </div>
            </section>

            <div class="completion-sync-note guide-anim" style="--guide-delay: 700ms">
                <span class="completion-sync-dot" aria-hidden="true" />
                <span>Results are saved for educator review.</span>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.completion-confetti {
    position: fixed;
    inset: 0;
    z-index: 4;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.completion-shell {
    position: relative;
    z-index: 6;
    display: grid;
    gap: 1rem;
    width: 100%;
}

.completion-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 2px solid var(--rd-story-border);
    border-radius: 24px;
    background: linear-gradient(135deg, rgba(245, 133, 73, 0.09), rgba(16, 185, 129, 0.08));
    padding: clamp(1rem, 3vw, 1.35rem);
    box-shadow: 0 5px 0 var(--rd-lip), 0 7px 0 var(--rd-lip-dark), 0 16px 22px -8px var(--rd-shadow);
    text-align: left;
}

.completion-hero-icon,
.completion-tile-icon,
.completion-score-icon,
.completion-section-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    color: var(--rd-primary-orange);
}

.completion-hero-icon {
    width: 3.8rem;
    height: 3.8rem;
    border-radius: 1.1rem;
    background: rgba(245, 133, 73, 0.13);
}

.completion-hero-copy {
    min-width: 0;
}

.completion-hero-title {
    color: var(--rd-text-main);
    font-size: clamp(1.2rem, 3vw, 1.7rem);
    font-weight: 900;
    line-height: 1.12;
}

.completion-hero-text {
    margin-top: 0.25rem;
    color: var(--rd-text-muted);
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.35;
}

.completion-tile-grid,
.completion-score-grid,
.completion-note-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 760px) {
    .completion-tile-grid,
    .completion-score-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .completion-note-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.completion-tile {
    justify-items: center;
    padding: 1rem;
    text-align: center;
}

.completion-tile-icon {
    width: 2.65rem;
    height: 2.65rem;
    border-radius: 0.9rem;
}

.completion-tile--green .completion-tile-icon {
    background: #ecfdf5;
    color: #059669;
}

.completion-tile--orange .completion-tile-icon {
    background: rgba(245, 133, 73, 0.12);
    color: var(--rd-primary-orange);
}

.completion-tile--gold .completion-tile-icon {
    background: #fffbeb;
    color: #b45309;
}

.completion-tile-value {
    color: var(--rd-text-main);
    font-size: clamp(1.15rem, 2.3vw, 1.45rem);
    font-weight: 900;
    line-height: 1.12;
}

.completion-tile-caption {
    color: var(--rd-text-muted);
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.3;
}

.completion-score-card {
    align-content: start;
    padding: 1rem;
    text-align: left;
}

.completion-score-card--progress {
    border-color: rgba(16, 185, 129, 0.24);
    background: #ecfdf5;
}

.completion-score-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}

.completion-score-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 0.7rem;
    background: rgba(245, 133, 73, 0.12);
}

.completion-score-title {
    color: var(--rd-text-main);
    font-size: 0.98rem;
    font-weight: 900;
    line-height: 1.18;
}

.completion-metric-list {
    display: grid;
    gap: 0.55rem;
    margin-top: 0.9rem;
}

.completion-metric {
    display: grid;
    gap: 0.15rem;
    border: 1.5px solid rgba(54, 83, 101, 0.1);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.72);
    padding: 0.7rem 0.8rem;
}

.completion-metric-label {
    color: var(--rd-text-muted);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    line-height: 1;
    text-transform: uppercase;
}

.completion-metric-value {
    color: var(--rd-text-main);
    font-size: 1.08rem;
    font-weight: 900;
    line-height: 1.15;
}

.completion-metric-value--positive,
.completion-score-message {
    color: #047857;
}

.completion-score-message {
    margin-top: 0.85rem;
    font-size: 0.82rem;
    font-weight: 900;
    line-height: 1.28;
}

.completion-certificate,
.completion-agent-notes {
    display: grid;
    gap: 1rem;
    border: 2px solid var(--rd-story-border);
    border-radius: 24px;
    background: var(--rd-story-surface);
    padding: clamp(1rem, 3vw, 1.4rem);
    box-shadow: 0 5px 0 var(--rd-lip), 0 7px 0 var(--rd-lip-dark), 0 16px 22px -8px var(--rd-shadow);
}

.completion-section-heading {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-align: left;
}

.completion-section-heading h2 {
    color: var(--rd-text-main);
    font-size: clamp(1rem, 2.2vw, 1.28rem);
    font-weight: 900;
    line-height: 1.15;
}

.completion-section-icon {
    width: 2.45rem;
    height: 2.45rem;
    border-radius: 0.85rem;
    background: rgba(245, 133, 73, 0.12);
}

.completion-section-icon--muted {
    background: rgba(54, 83, 101, 0.08);
    color: var(--rd-text-muted);
}

.completion-note {
    border: 1.5px solid rgba(54, 83, 101, 0.1);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.72);
    padding: 0.9rem 1rem;
    text-align: left;
}

.completion-note-name {
    color: var(--rd-primary-orange);
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.completion-note-role {
    margin-top: 0.12rem;
    color: var(--rd-text-muted);
    font-size: 0.76rem;
    font-weight: 800;
}

.completion-note-text {
    margin-top: 0.6rem;
    color: var(--rd-text-main);
    font-size: 0.9rem;
    font-weight: 850;
    line-height: 1.35;
}

.completion-sync-note {
    display: inline-flex;
    align-items: center;
    justify-self: center;
    gap: 0.5rem;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.08);
    padding: 0.62rem 0.9rem;
    color: #047857;
    font-size: 0.8rem;
    font-weight: 900;
}

.completion-sync-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 999px;
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45);
    animation: syncPulse 1.8s ease-in-out infinite;
}

@keyframes syncPulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45);
    }
    50% {
        box-shadow: 0 0 0 0.35rem rgba(16, 185, 129, 0);
    }
}

@media (max-width: 560px) {
    .completion-hero {
        align-items: flex-start;
    }
}

@media (prefers-reduced-motion: reduce) {
    .completion-sync-dot {
        animation: none;
    }
}
</style>
