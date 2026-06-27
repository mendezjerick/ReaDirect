<script setup>
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Loader2 } from 'lucide-vue-next';
import SyncStatusBadge from '../../Components/SyncStatusBadge.vue';

const form = useForm({ learner_code: 'RD-1001' });
const submit = () => form.post('/learner/access');
</script>

<template>
    <div class="learner-autumn-shell flex min-h-screen flex-col">

        <!-- Header: matches assessment header rd-card pattern -->
        <header class="acc-header sticky top-0 z-20 px-4 pb-2 pt-2">
            <div class="rd-card mx-auto max-w-5xl">
                <div class="rd-card__face flex min-h-14 items-center justify-between gap-3 px-5 py-2">
                    <a href="/" class="inline-flex items-center gap-2.5 text-xl font-black transition hover:scale-[1.02]" style="color: var(--rd-text-main)">
                        <span class="grid size-10 place-items-center rounded-xl border-2 border-[#D9652F] bg-primary text-sm font-black text-white" style="box-shadow: 0 5px 0 #B84B24, 0 8px 14px rgba(54,83,101,0.18), inset 0 2px 0 rgba(255,255,255,0.35)">
                            Re
                        </span>
                        <span class="hidden sm:inline">ReaDirect</span>
                    </a>
                    <SyncStatusBadge />
                </div>
            </div>
        </header>

        <!-- Main: centered vertically -->
        <main class="relative z-10 flex flex-1 items-center justify-center px-4 py-8">
            <div class="acc-book rd-card w-full max-w-4xl">

                <!-- Split book interior -->
                <div class="acc-book-face">

                    <!-- LEFT: Book cover — deep autumn teal -->
                    <div class="acc-cover">
                        <!-- Subtle diagonal highlight -->
                        <div class="acc-cover-glare" aria-hidden="true" />

                        <!-- Ornament -->
                        <span class="acc-ornament" aria-hidden="true">✦</span>

                        <!-- Brand eyebrow -->
                        <p class="acc-cover-brand">ReaDirect</p>

                        <!-- Big welcome headline -->
                        <h1 class="acc-cover-title">
                            Welcome,<br>Reader.
                        </h1>

                        <!-- Subtitle -->
                        <p class="acc-cover-sub">
                            Enter your learner code to start your reading journey.
                        </p>

                        <!-- Bottom book icon -->
                        <div class="acc-cover-icon" aria-hidden="true">
                            <BookOpen class="size-6" />
                        </div>
                    </div>

                    <!-- RIGHT: Form — warm parchment -->
                    <div class="acc-form-side">
                        <form class="acc-form" @submit.prevent="submit">

                            <!-- Label -->
                            <label for="learner_code" class="acc-form-label">
                                Your learner code
                            </label>

                            <!-- Input -->
                            <input
                                id="learner_code"
                                v-model="form.learner_code"
                                type="text"
                                autocomplete="off"
                                spellcheck="false"
                                :class="['acc-input', form.errors.learner_code ? 'acc-input--error' : '']"
                                placeholder="RD-0000"
                            >

                            <!-- Error message -->
                            <Transition name="acc-err">
                                <div v-if="form.errors.learner_code" class="acc-error">
                                    <span class="acc-error-dot">!</span>
                                    <p class="acc-error-msg">{{ form.errors.learner_code }}</p>
                                </div>
                            </Transition>

                            <!-- Helper text -->
                            <p v-if="!form.errors.learner_code" class="acc-helper">
                                Ask your teacher if you need your learner code.
                            </p>

                            <!-- Submit -->
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="acc-submit rd-submit-button"
                            >
                                <template v-if="form.processing">
                                    <Loader2 class="size-5 animate-spin" />
                                    <span>Checking…</span>
                                </template>
                                <template v-else>
                                    <span>Continue</span>
                                    <ArrowRight class="size-5" />
                                </template>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* ─── Header entrance ────────────────────────────────── */
.acc-header {
    animation: accSlide 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes accSlide {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Book card entrance ─────────────────────────────── */
.acc-book {
    animation: accRise 0.65s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    animation-delay: 60ms;
}

@keyframes accRise {
    from { opacity: 0; transform: translateY(22px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ─── Split interior ─────────────────────────────────── */
.acc-book-face {
    display: grid;
    grid-template-columns: 1fr;
    overflow: hidden;
    border: 1.5px solid var(--rd-face-border);
    border-radius: var(--rd-radius-face); /* 18px */
}

@media (min-width: 580px) {
    .acc-book-face {
        grid-template-columns: 1fr 1.15fr;
        min-height: 400px;
    }
}

/* ─── LEFT cover panel ───────────────────────────────── */
.acc-cover {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.9rem;
    padding: 2.25rem 1.875rem 2.75rem;
    background: linear-gradient(155deg, #365365 0%, #2A4557 100%);
    overflow: hidden;
}

/* Diagonal highlight shimmer */
.acc-cover-glare {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(123, 161, 181, 0.18) 0%, transparent 55%);
    pointer-events: none;
}

/* Gold ornament — top right */
.acc-ornament {
    position: absolute;
    top: 1.25rem;
    right: 1.375rem;
    font-size: 1.25rem;
    font-weight: 900;
    color: rgba(238, 193, 112, 0.5);
    line-height: 1;
    pointer-events: none;
}

.acc-cover-brand {
    font-size: 0.65rem;
    font-weight: 900;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: rgba(238, 193, 112, 0.7);
    animation: accFadeUp 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 150ms;
}

.acc-cover-title {
    font-size: clamp(1.85rem, 4vw, 2.8rem);
    font-weight: 900;
    line-height: 1.07;
    letter-spacing: -0.02em;
    color: #FFFDF8;
    animation: accFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 210ms;
}

.acc-cover-sub {
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.55;
    color: rgba(255, 253, 248, 0.62);
    animation: accFadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 280ms;
}

/* Book icon badge — bottom right of cover */
.acc-cover-icon {
    position: absolute;
    bottom: 1.375rem;
    right: 1.375rem;
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.75rem;
    background: rgba(238, 193, 112, 0.12);
    border: 1.5px solid rgba(238, 193, 112, 0.22);
    color: rgba(238, 193, 112, 0.65);
}

/* ─── RIGHT form panel ───────────────────────────────── */
.acc-form-side {
    display: flex;
    align-items: center;
    padding: 2rem 1.875rem;
    background: var(--rd-face-surface); /* warm #FFFDF8 */
}

.acc-form {
    display: grid;
    gap: 0.875rem;
    width: 100%;
}

.acc-form-label {
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--rd-text-muted);
    animation: accFadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 200ms;
}

.acc-input {
    width: 100%;
    padding: 0.875rem 1.25rem;
    border-radius: 14px;
    border: 2px solid var(--rd-frame-border);
    background: #fff;
    font-size: clamp(1.4rem, 3vw, 1.75rem);
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--rd-text-main);
    transition: border-color 150ms ease, box-shadow 150ms ease;
    animation: accFadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 270ms;
}

.acc-input::placeholder {
    color: rgba(95, 111, 120, 0.28);
    font-weight: 700;
}

.acc-input:focus {
    outline: none;
    border-color: var(--rd-primary-orange);
    box-shadow: 0 0 0 4px rgba(245, 133, 73, 0.1);
}

.acc-input--error {
    border-color: #f87171;
    color: #b91c1c;
}

.acc-input--error:focus {
    border-color: #f87171;
    box-shadow: 0 0 0 4px rgba(248, 113, 113, 0.1);
}

/* ─── Error box ──────────────────────────────────────── */
.acc-error {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.75rem 0.875rem;
    border-radius: 12px;
    background: #fff1f2;
    border: 1px solid rgba(248, 113, 113, 0.3);
}

.acc-error-dot {
    display: grid;
    place-items: center;
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 50%;
    background: #fee2e2;
    font-size: 0.6rem;
    font-weight: 900;
    color: #ef4444;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.acc-error-msg {
    font-size: 0.8125rem;
    font-weight: 700;
    line-height: 1.4;
    color: #dc2626;
    margin: 0;
}

/* ─── Helper text ────────────────────────────────────── */
.acc-helper {
    font-size: 0.73rem;
    font-weight: 600;
    color: var(--rd-text-muted);
    text-align: center;
    opacity: 0.65;
    animation: accFadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 340ms;
}

/* ─── Submit button ──────────────────────────────────── */
/* rd-submit-button provides: gradient, border, shadow, uppercase, font-weight */
.acc-submit {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.9rem 1.5rem;
    font-size: 1rem;
    animation: accFadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 340ms;
}

/* ─── Shared fade-up keyframe ────────────────────────── */
@keyframes accFadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Error transition ───────────────────────────────── */
.acc-err-enter-active {
    animation: accErrIn 0.28s ease-out both;
}
.acc-err-leave-active {
    animation: accErrIn 0.2s ease-in reverse both;
}
@keyframes accErrIn {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Mobile: compact cover ──────────────────────────── */
@media (max-width: 579px) {
    .acc-cover {
        padding: 1.625rem 1.5rem 1.875rem;
        gap: 0.65rem;
    }

    .acc-cover-icon {
        display: none;
    }

    .acc-form-side {
        padding: 1.625rem 1.5rem;
    }
}

/* ─── Reduced motion ─────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .acc-header,
    .acc-book,
    .acc-cover-brand,
    .acc-cover-title,
    .acc-cover-sub,
    .acc-form-label,
    .acc-input,
    .acc-helper,
    .acc-submit {
        animation: none;
    }
}
</style>
