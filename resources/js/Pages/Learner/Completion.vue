<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import { CheckCircle2, Home, Trophy, Volume2, VolumeX } from "lucide-vue-next";
import LearnerLayout from "../../Layouts/LearnerLayout.vue";
import AgentVideoPlayer from "../../Components/Agents/AgentVideoPlayer.vue";
import CompletionCertificate from "../../Components/Learner/CompletionCertificate.vue";

const props = defineProps({
    learner: { type: Object, default: null },
    resultSummary: {
        type: Object,
        default: () => ({
            cards: [],
            fallbackMessage: "Your final reading check has been completed.",
        }),
    },
    agentMessages: { type: Array, default: () => [] },
    thankYouUrl: { type: String, default: "/learner/completion/thank-you" },
    homeUrl: { type: String, default: "/" },
});

const form = useForm({});
const activeAudio = ref(null);
const activeAgent = ref(null);
const voiceStatus = ref("");
const showCertificate = ref(false);
const emojiContainer = ref(null);

const firstName = computed(() => props.learner?.first_name ?? "Friend");
const lastName = computed(() => props.learner?.last_name ?? "");
const cards = computed(() => props.resultSummary?.cards ?? []);
const hasAnyMetrics = computed(() =>
    cards.value.some((c) => (c.metrics ?? []).length > 0),
);

/* ── Certificate data helpers ─────────────────── */
const certData = computed(() => {
    const finalCard = cards.value.find((c) => c.kind === "final") ?? {};
    const metric = (card, label) =>
        (card.metrics ?? []).find(
            (m) => m.label?.toLowerCase() === label.toLowerCase(),
        )?.value ?? "";
    return {
        readingLevel:
            metric(finalCard, "reading level") ||
            metric(finalCard, "reading classification"),
        accuracyScore: metric(finalCard, "reading accuracy"),
        crlaLevel:
            metric(finalCard, "crla level") || metric(finalCard, "crla score"),
        completedAt: props.resultSummary?.completedAt ?? "",
    };
});

/* ── Party emoji burst ────────────────────────── */
const PARTY_EMOJIS = [
    "🎉",
    "🎊",
    "⭐",
    "🌟",
    "✨",
    "🏆",
    "🥳",
    "📚",
    "💫",
    "🎈",
];

const spawnEmoji = () => {
    const el = emojiContainer.value;
    if (!el) return;
    const span = document.createElement("span");
    span.textContent =
        PARTY_EMOJIS[Math.floor(Math.random() * PARTY_EMOJIS.length)];
    const startX = Math.random() * 100;
    const drift = (Math.random() - 0.5) * 180;
    const size = Math.random() * 18 + 18;
    const dur = Math.random() * 2000 + 2500;
    span.style.cssText = `
        position: absolute;
        left: ${startX}%;
        bottom: 0;
        font-size: ${size}px;
        opacity: 1;
        pointer-events: none;
        user-select: none;
        animation: emojiFloat ${dur}ms cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        --drift: ${drift}px;
    `;
    el.appendChild(span);
    setTimeout(() => span.remove(), dur + 100);
};

let emojiInterval = null;
const startParty = () => {
    // Initial burst
    for (let i = 0; i < 12; i++) {
        setTimeout(spawnEmoji, i * 120);
    }
    // Trickle for 8s
    emojiInterval = setInterval(spawnEmoji, 350);
    setTimeout(() => {
        clearInterval(emojiInterval);
        emojiInterval = null;
    }, 8000);
};

/* ── Square confetti (small dots like Image 1) ── */
const confettiCanvas = ref(null);
let animFrame = null;

const startConfetti = () => {
    const canvas = confettiCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    const resize = () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    };
    window.addEventListener("resize", resize);
    resize();

    const COLORS = [
        "#00236f",
        "#fd761a",
        "#27c38a",
        "#b6c4ff",
        "#ffd700",
        "#ff6b6b",
        "#a78bfa",
    ];
    const particles = Array.from({ length: 55 }, () => {
        const o = {};
        const reset = (init = false) => {
            o.x = Math.random() * canvas.width;
            o.y = init ? Math.random() * canvas.height - canvas.height : -16;
            o.w = Math.random() * 9 + 4;
            o.h = Math.random() * 5 + 3;
            o.vy = Math.random() * 2.5 + 1.5;
            o.vx = Math.random() * 1.8 - 0.9;
            o.rot = Math.random() * 360;
            o.rs = Math.random() * 4 - 2;
            o.color = COLORS[Math.floor(Math.random() * COLORS.length)];
            o.opacity = Math.random() * 0.4 + 0.6;
        };
        reset(true);
        o.reset = reset;
        return o;
    });

    const draw = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach((p) => {
            p.y += p.vy;
            p.x += p.vx;
            p.rot += p.rs;
            if (p.y > canvas.height) p.reset();
            ctx.save();
            ctx.globalAlpha = p.opacity;
            ctx.translate(p.x, p.y);
            ctx.rotate((p.rot * Math.PI) / 180);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            ctx.restore();
        });
        animFrame = requestAnimationFrame(draw);
    };
    draw();
};

/* ── Voice ────────────────────────────────────── */
const csrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") ?? "";

const stopVoices = () => {
    if (activeAudio.value) {
        activeAudio.value.pause();
        activeAudio.value.currentTime = 0;
        activeAudio.value = null;
    }
    if (typeof window !== "undefined") {
        window.dispatchEvent(new CustomEvent("readirect:stop-agent-audio"));
        window.dispatchEvent(new CustomEvent("readirect:stop-agent-speech"));
    }
    activeAgent.value = null;
};

const playAgent = async (agent) => {
    const message = [agent.resultMessage, agent.message]
        .filter(Boolean)
        .join(" ");
    const agentKey = agent.agentType;
    stopVoices();
    activeAgent.value = agentKey;
    voiceStatus.value = "";
    try {
        const res = await fetch("/agent-voice/synthesize", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken(),
            },
            body: JSON.stringify({ agent: agent.agentType, text: message }),
        });
        const payload = res.ok ? await res.json() : {};
        if (payload.audio_url) {
            const audio = new Audio(payload.audio_url);
            activeAudio.value = audio;
            audio.onended = () => {
                if (activeAgent.value === agentKey) activeAgent.value = null;
                activeAudio.value = null;
            };
            audio.onerror = () => {
                if (activeAgent.value === agentKey) {
                    voiceStatus.value =
                        "Voice unavailable — read each message here.";
                    activeAgent.value = null;
                }
                activeAudio.value = null;
            };
            await audio.play();
            return;
        }
    } catch {
        /* silent */
    }
    voiceStatus.value = "Voice unavailable — read each message here.";
    activeAgent.value = null;
};

const submitThankYou = () => {
    stopVoices();
    form.post(props.thankYouUrl, { preserveScroll: false });
};

onMounted(() => {
    startConfetti();
    startParty();
});
onBeforeUnmount(() => {
    stopVoices();
    if (animFrame) cancelAnimationFrame(animFrame);
    if (emojiInterval) clearInterval(emojiInterval);
});
</script>

<template>
    <LearnerLayout :progress="100">
        <!-- Square confetti canvas (matches Image 1 scattered dots) -->
        <canvas
            ref="confettiCanvas"
            class="confetti-canvas"
            aria-hidden="true"
        />

        <!-- Emoji burst container -->
        <div ref="emojiContainer" class="emoji-container" aria-hidden="true" />

        <section class="completion-shell mx-auto grid w-full gap-6">
            <!-- ════════════════════════════════════════
                 HERO CARD  (Stitch-AI / Image 1 style)
                 ════════════════════════════════════════ -->
            <div class="hero-card anim-card">
                <!-- Pulsing success icon -->
                <div class="hero-icon-wrap" aria-hidden="true">
                    <div class="hero-icon-ring" />
                    <div class="hero-icon">
                        <svg
                            width="40"
                            height="40"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                </div>

                <!-- Text -->
                <h1 class="hero-title anim-pop">
                    Great job, <span class="hero-name">{{ firstName }}!</span>
                </h1>
                <p class="hero-subtitle">Assessment Complete 🎊</p>

                <!-- 3 quick-stat bento tiles -->
                <div class="hero-stats">
                    <div class="stat-tile">
                        <span class="stat-label">Items</span>
                        <span class="stat-value text-primary">All Done</span>
                        <span class="stat-caption">Finished</span>
                    </div>
                    <div class="stat-tile stat-tile--accent">
                        <span class="stat-label">Reading Journey</span>
                        <span class="stat-value text-emerald-600">100%</span>
                        <span class="stat-caption">Completed</span>
                    </div>
                    <div class="stat-tile">
                        <span class="stat-label">Accuracy</span>
                        <span
                            class="stat-value"
                            :class="
                                certData.accuracyScore
                                    ? 'text-primary'
                                    : 'text-slate-400'
                            "
                        >
                            {{ certData.accuracyScore || "—" }}
                        </span>
                        <span class="stat-caption">Est. Reading Level</span>
                    </div>
                </div>

                <!-- Sync row -->
                <div class="hero-sync" aria-live="polite">
                    <span class="sync-dot" aria-hidden="true" />
                    <span
                        >Results are being synced and encrypted for educator
                        review.</span
                    >
                </div>

                <!-- Agents celebrating row -->
                <div
                    class="hero-agents"
                    aria-label="Your learning agents celebrating with you"
                >
                    <!-- Miss Vivian static portrait -->
                    <div class="hero-agent-bubble">
                        <div class="hero-agent-avatar">
                            <AgentVideoPlayer
                                agent="Vivian"
                                action="congrats"
                                allow-congrats
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <span class="hero-agent-name">Miss Vivian</span>
                    </div>
                    <!-- Miss Ciel static portrait -->
                    <div class="hero-agent-bubble hero-agent-bubble--center">
                        <div class="hero-agent-avatar hero-agent-avatar--lg">
                            <AgentVideoPlayer
                                agent="Ciel"
                                action="congrats"
                                allow-congrats
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <span class="hero-agent-name">Miss Ciel</span>
                    </div>
                    <!-- Miss Estelle static portrait -->
                    <div class="hero-agent-bubble">
                        <div class="hero-agent-avatar">
                            <AgentVideoPlayer
                                agent="Estelle"
                                action="congrats"
                                allow-congrats
                                class="h-full w-full object-contain"
                            />
                        </div>
                        <span class="hero-agent-name">Miss Estelle</span>
                    </div>
                </div>

                <!-- CTA -->
                <button
                    id="btn-thank-you-hero"
                    type="button"
                    class="hero-cta"
                    :disabled="form.processing"
                    @click="submitThankYou"
                >
                    Finish and Submit
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                </button>
            </div>

            <!-- ════════════════════════════════════════
                 DETAILED SCORE CARDS  (Image 2 content)
                 ════════════════════════════════════════ -->
            <div
                v-if="hasAnyMetrics"
                class="anim-stagger grid gap-5 md:grid-cols-3"
            >
                <article
                    v-for="card in cards"
                    :key="card.title"
                    class="score-card"
                    :class="{
                        'score-card--progress': card.kind === 'progress',
                    }"
                >
                    <!-- Card header -->
                    <div class="score-card-header">
                        <span class="score-card-icon" aria-hidden="true">
                            <CheckCircle2 class="size-4" />
                        </span>
                        <h2 class="score-card-title">{{ card.title }}</h2>
                    </div>

                    <!-- Metrics -->
                    <div class="mt-4 grid gap-2.5">
                        <div
                            v-for="metric in card.metrics"
                            :key="metric.label"
                            class="metric-row"
                        >
                            <p class="metric-label">{{ metric.label }}</p>
                            <p
                                class="metric-value"
                                :class="
                                    card.kind === 'progress' &&
                                    String(metric.value).startsWith('+')
                                        ? 'text-emerald-600'
                                        : ''
                                "
                            >
                                {{ metric.value }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="card.message"
                        class="mt-3 text-[13px] font-black leading-snug text-emerald-600"
                    >
                        {{ card.message }}
                    </p>
                </article>
            </div>

            <!-- Fallback when no metrics -->
            <p
                v-else
                class="rounded-[24px] border border-slate-200/60 bg-white px-5 py-4 text-center text-[15px] font-semibold text-primary shadow-sm"
            >
                {{ resultSummary.fallbackMessage }}
            </p>

            <!-- ════════════════════════════════════════
                 BENTO ROW  (Assessment / Sync / Cert)
                 ════════════════════════════════════════ -->
            <div class="anim-slide-up grid grid-cols-1 gap-4 sm:grid-cols-3">
                <!-- Assessment tile -->
                <div class="bento-tile">
                    <span class="bento-label">Assessment</span>
                    <span class="bento-value text-primary">Complete</span>
                    <span class="bento-caption">All tasks finished 🎯</span>
                </div>

                <!-- Sync tile -->
                <div class="bento-tile bento-tile--green">
                    <div class="flex items-center justify-center gap-2">
                        <span
                            class="sync-dot sync-dot--sm"
                            aria-hidden="true"
                        />
                        <span class="bento-label text-emerald-600"
                            >Syncing</span
                        >
                    </div>
                    <span class="bento-caption text-slate-500 mt-1"
                        >Results encrypted for educator review</span
                    >
                </div>

                <!-- Certificate tile -->
                <div
                    class="bento-tile bento-tile--amber cursor-pointer select-none transition-all hover:-translate-y-0.5 hover:shadow-amber-200/60"
                    role="button"
                    tabindex="0"
                    :aria-expanded="showCertificate"
                    aria-label="Toggle your certificate"
                    @click="showCertificate = !showCertificate"
                    @keydown.enter="showCertificate = !showCertificate"
                    @keydown.space.prevent="showCertificate = !showCertificate"
                >
                    <span class="bento-label text-amber-600">Certificate</span>
                    <span class="bento-value text-amber-700">{{
                        showCertificate ? "Hide ↑" : "View ✦"
                    }}</span>
                    <span class="bento-caption text-amber-600"
                        >Click to
                        {{
                            showCertificate
                                ? "collapse"
                                : "see your certificate"
                        }}</span
                    >
                </div>
            </div>

            <!-- ════════════════════════════════════════
                 CERTIFICATE (collapsible, landscape)
                 ════════════════════════════════════════ -->
            <Transition name="cert-fade">
                <div v-if="showCertificate" class="cert-section">
                    <div class="cert-section-header">
                        <Trophy
                            class="size-5 text-amber-600"
                            aria-hidden="true"
                        />
                        <span>Your Certificate of Completion</span>
                    </div>
                    <CompletionCertificate
                        :first-name="firstName"
                        :last-name="lastName"
                        :completed-at="certData.completedAt"
                        :reading-level="certData.readingLevel"
                        :accuracy-score="certData.accuracyScore"
                        :crla-level="certData.crlaLevel"
                    />
                </div>
            </Transition>

            <!-- ════════════════════════════════════════
                 AGENT MESSAGES
                 ════════════════════════════════════════ -->
            <section
                v-if="agentMessages.length"
                class="anim-slide-up rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/30"
                aria-label="Messages from your learning agents"
            >
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-[16px] font-black text-slate-800">
                        Your agents are proud of you 🌟
                    </h2>
                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-2xl bg-slate-50 text-slate-400 ring-1 ring-slate-200/60 transition hover:bg-gradient-to-br hover:from-primary hover:to-blue-600 hover:text-white hover:ring-0"
                        aria-label="Stop voice playback"
                        @click="stopVoices"
                    >
                        <VolumeX class="size-5" aria-hidden="true" />
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <article
                        v-for="agent in agentMessages"
                        :key="agent.name"
                        class="rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-4 shadow-sm"
                    >
                        <!-- Agent face avatar -->
                        <div class="agent-card-avatar mb-3">
                            <div class="agent-card-img-wrap">
                                <AgentVideoPlayer
                                    :agent="agent.agentType"
                                    action="congrats"
                                    :alt="agent.name"
                                    allow-congrats
                                    class="agent-face-media"
                                    :class="`agent-face-media--${agent.agentType}`"
                                />
                            </div>
                        </div>

                        <div
                            class="flex items-start justify-between gap-3 mb-3"
                        >
                            <div>
                                <p
                                    class="text-[14px] font-black uppercase tracking-widest text-primary"
                                >
                                    {{ agent.name }}
                                </p>
                                <p
                                    class="text-[12px] font-semibold text-slate-400"
                                >
                                    {{ agent.role }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-[20px] bg-gradient-to-r from-primary to-blue-600 px-3 py-2 text-[12px] font-black text-white shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5 hover:shadow-xl disabled:opacity-70 disabled:hover:translate-y-0"
                                :disabled="activeAgent === agent.agentType"
                                :aria-label="`Play voice for ${agent.name}`"
                                @click="playAgent(agent)"
                            >
                                <Volume2 class="size-3.5" aria-hidden="true" />
                                {{
                                    activeAgent === agent.agentType
                                        ? "Playing…"
                                        : "Play"
                                }}
                            </button>
                        </div>
                        <p
                            v-if="agent.resultMessage"
                            class="text-[13px] font-black leading-snug text-slate-800 mb-1"
                        >
                            {{ agent.resultMessage }}
                        </p>
                        <p
                            class="text-[13px] font-semibold leading-snug text-slate-600"
                        >
                            {{ agent.message }}
                        </p>
                    </article>
                </div>

                <p
                    v-if="voiceStatus"
                    class="mt-4 rounded-[20px] bg-amber-50 px-4 py-3 text-[13px] font-semibold text-amber-700 ring-1 ring-amber-200/60"
                    role="alert"
                >
                    {{ voiceStatus }}
                </p>
            </section>

            <!-- ════════════════════════════════════════
                 FOOTER ACTION
                 ════════════════════════════════════════ -->
            <footer
                class="anim-slide-up grid gap-3 rounded-[32px] border border-slate-200/80 bg-white p-5 text-center shadow-xl shadow-slate-200/30 sm:p-6"
            >
                <p class="text-[14px] font-semibold text-slate-400">
                    You may now return to the home page.
                </p>
                <button
                    id="btn-thank-you"
                    type="button"
                    class="mx-auto inline-flex w-full max-w-xl items-center justify-center gap-3 rounded-[20px] bg-gradient-to-r from-primary to-blue-600 px-8 py-5 text-2xl font-black text-white shadow-xl shadow-primary/20 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-primary/20 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 sm:text-3xl"
                    :disabled="form.processing"
                    @click="submitThankYou"
                >
                    <Home class="size-7 sm:size-8" aria-hidden="true" />
                    Thank You
                </button>
            </footer>
        </section>
    </LearnerLayout>
</template>

<style scoped>
/* ═══ Layout shell ════════════════════════════════ */
.completion-shell {
    max-width: min(100%, 88rem);
    position: relative;
}

/* ═══ Confetti canvas ════════════════════════════ */
.confetti-canvas {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 5;
}

/* ═══ Emoji burst ════════════════════════════════ */
.emoji-container {
    position: fixed;
    inset: 0;
    overflow: hidden;
    pointer-events: none;
    z-index: 6;
}

@keyframes emojiFloat {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 1;
    }
    80% {
        opacity: 0.9;
    }
    100% {
        transform: translateY(-110vh) translateX(var(--drift)) rotate(360deg);
        opacity: 0;
    }
}

/* ═══ HERO CARD ══════════════════════════════════ */
.hero-card {
    max-width: 680px;
    margin: 0 auto;
    width: 100%;
    background: #ffffff;
    border: 1.5px solid rgba(0, 35, 111, 0.1);
    border-radius: 28px;
    padding: 40px 36px 36px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    text-align: center;
    box-shadow:
        0 0 0 6px rgba(0, 35, 111, 0.04),
        0 24px 64px -16px rgba(0, 35, 111, 0.12),
        0 4px 20px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

/* Subtle shimmer line at top */
.hero-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(
        90deg,
        #00236f,
        #4059aa,
        #27c38a,
        #fd761a,
        #4059aa,
        #00236f
    );
    background-size: 200% 100%;
    animation: shimmer 3s linear infinite;
}
@keyframes shimmer {
    0% {
        background-position: 0% 0%;
    }
    100% {
        background-position: 200% 0%;
    }
}

/* ── Hero icon ── */
.hero-icon-wrap {
    position: relative;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.hero-icon-ring {
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    background: rgba(39, 195, 138, 0.12);
    animation: ringPulse 2.5s ease-in-out infinite;
}
@keyframes ringPulse {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.15);
        opacity: 0.5;
    }
}
.hero-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #27c38a, #4edea3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 8px 24px rgba(39, 195, 138, 0.35);
    animation: iconBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.3s both;
}
@keyframes iconBounce {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

/* ── Hero text ── */
.hero-title {
    font-size: clamp(28px, 5vw, 42px);
    font-weight: 800;
    color: #0f172a;
    line-height: 1.15;
    margin-bottom: 6px;
    letter-spacing: -0.02em;
}
.hero-name {
    background: linear-gradient(135deg, #00236f, #4059aa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-subtitle {
    font-size: 16px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 28px;
}

/* ── Stats row ── */
.hero-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    width: 100%;
    margin-bottom: 24px;
}
.stat-tile {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.stat-tile--accent {
    background: rgba(39, 195, 138, 0.06);
    border-color: rgba(39, 195, 138, 0.25);
}
.stat-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
}
.stat-value {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 2px;
}
.stat-caption {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}

/* ── Sync row ── */
.hero-sync {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 24px;
}
.sync-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #27c38a;
    flex-shrink: 0;
    animation: pulse-sync 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.sync-dot--sm {
    width: 6px;
    height: 6px;
}
@keyframes pulse-sync {
    0%,
    100% {
        opacity: 1;
        box-shadow: 0 0 0 0 rgba(39, 195, 138, 0.5);
    }
    50% {
        opacity: 0.7;
        box-shadow: 0 0 0 4px rgba(39, 195, 138, 0);
    }
}

/* ── CTA ── */
.hero-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    max-width: 380px;
    padding: 16px 32px;
    background: linear-gradient(135deg, #00236f, #1e3a8a, #4059aa);
    color: white;
    border: none;
    border-radius: 16px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
    box-shadow:
        0 8px 24px rgba(0, 35, 111, 0.28),
        0 2px 8px rgba(0, 35, 111, 0.15);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    letter-spacing: 0.01em;
}
.hero-cta:hover {
    transform: translateY(-2px);
    box-shadow:
        0 12px 32px rgba(0, 35, 111, 0.38),
        0 4px 12px rgba(0, 35, 111, 0.2);
}
.hero-cta:active {
    transform: translateY(0);
}
.hero-cta:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
}

/* ═══ SCORE CARDS ════════════════════════════════ */
.score-card {
    border-radius: 28px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}
.score-card--progress {
    border-color: rgba(39, 195, 138, 0.3);
    background: rgba(39, 195, 138, 0.03);
}
.score-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
}
.score-card-icon {
    display: flex;
    width: 32px;
    height: 32px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: linear-gradient(135deg, #00236f, #4059aa);
    color: white;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 35, 111, 0.2);
}
.score-card-title {
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.2;
}
.metric-row {
    border-radius: 16px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: rgba(248, 250, 252, 0.6);
    padding: 10px 14px;
}
.metric-label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 2px;
}
.metric-value {
    font-size: 19px;
    font-weight: 800;
    color: #1e293b;
    line-height: 1.2;
}

/* ═══ BENTO ROW ══════════════════════════════════ */
.bento-tile {
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 2px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}
.bento-tile--green {
    border-color: rgba(39, 195, 138, 0.3);
    background: rgba(39, 195, 138, 0.04);
}
.bento-tile--amber {
    border-color: rgba(251, 191, 36, 0.4);
    background: rgba(254, 243, 199, 0.35);
}
.bento-label {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94a3b8;
}
.bento-value {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 2px;
}
.bento-caption {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}

/* ═══ CERTIFICATE SECTION ════════════════════════ */
.cert-section {
    border-radius: 28px;
    border: 2px dashed rgba(0, 35, 111, 0.15);
    background: linear-gradient(135deg, #f0f4ff 0%, #fefefe 60%, #f0f8f4 100%);
    padding: 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}
.cert-section-header {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 800;
    color: #92400e;
    background: linear-gradient(to right, #fef3c7, #fffbeb);
    padding: 8px 20px;
    border-radius: 50px;
    border: 1px solid #fde68a;
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.2);
}

/* ═══ ANIMATIONS ═════════════════════════════════ */
.cert-fade-enter-active,
.cert-fade-leave-active {
    transition: all 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}
.cert-fade-enter-from,
.cert-fade-leave-to {
    opacity: 0;
    transform: translateY(16px) scale(0.98);
}

.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from {
        opacity: 0;
        transform: scale(0.92) translateY(24px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.2s both;
}
@keyframes contentPop {
    from {
        opacity: 0;
        transform: scale(0.75);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
}
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(28px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) {
    animation-delay: 0ms;
}
.anim-stagger > *:nth-child(2) {
    animation-delay: 140ms;
}
.anim-stagger > *:nth-child(3) {
    animation-delay: 280ms;
}
@keyframes staggerIn {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ═══ MOBILE ═════════════════════════════════════ */
@media (max-width: 480px) {
    .hero-card {
        padding: 28px 20px 24px;
    }
    .hero-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    .stat-value {
        font-size: 16px;
    }
}

/* ═══ HERO AGENTS CELEBRATING ROW ══════════════ */
.hero-agents {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 16px;
    width: 100%;
    margin-bottom: 24px;
}

.hero-agent-bubble {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    animation: agentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
}
.hero-agent-bubble:nth-child(1) {
    animation-delay: 0.35s;
}
.hero-agent-bubble:nth-child(2) {
    animation-delay: 0.5s;
}
.hero-agent-bubble:nth-child(3) {
    animation-delay: 0.65s;
}

@keyframes agentPop {
    from {
        opacity: 0;
        transform: scale(0.5) translateY(20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.hero-agent-bubble--center {
    margin-bottom: 16px;
}

.hero-agent-avatar {
    width: 12rem;
    height: 12rem;
    border-radius: 1.5rem;
    background: #f8fafc;
    border: 4px solid #fff;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 15px -3px rgb(148 163 184 / 0.2);
}
.hero-agent-avatar--lg {
    width: 12rem;
    height: 12rem;
    border-radius: 1.5rem;
    background: #f8fafc;
    border: 4px solid #fff;
    box-shadow: 0 10px 15px -3px rgb(148 163 184 / 0.2);
}

.hero-agent-name {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
}

/* ═══ AGENT MESSAGE CARD AVATAR ════════════════ */
.agent-card-avatar {
    display: flex;
    justify-content: center;
}
.agent-card-img-wrap {
    width: 12rem;
    height: 12rem;
    max-width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 1.5rem;
    background: #f8fafc;
    border: 4px solid #fff;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 15px -3px rgb(148 163 184 / 0.2);
}
.agent-face-media {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 22%;
    display: block;
}

/* Optional per-agent face positioning */
.agent-face-media--assessment {
    object-position: center 18%;
}

.agent-face-media--coach_feedback {
    object-position: center 20%;
}

.agent-face-media--evaluator {
    object-position: center 18%;
}
</style>
