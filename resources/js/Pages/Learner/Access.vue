<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, BookOpenCheck, ClipboardCheck, Loader2, ShieldCheck, Star } from 'lucide-vue-next';
import SyncStatusBadge from '../../Components/SyncStatusBadge.vue';

const form = useForm({ learner_code: 'RD-1001' });
const submit = () => form.post('/learner/access');
</script>

<template>
    <div class="access-shell">
        <header class="access-topbar">
            <Link href="/" class="access-brand" aria-label="Back to ReaDirect home">
                <span class="access-brand-mark">
                    <BookOpenCheck class="size-5" stroke-width="2.8" />
                </span>
                <span>ReaDirect</span>
            </Link>

            <div class="access-header-actions">
                <SyncStatusBadge />
                <Link href="/" class="access-home-link">
                    <ArrowLeft class="size-4" stroke-width="3" />
                    Home
                </Link>
            </div>
        </header>

        <main class="access-main">
            <section class="access-card rd-card">
                <div class="access-card-face rd-card__face">
                    <div class="access-copy">
                        <span class="access-badge">
                            <ShieldCheck class="size-4" stroke-width="2.8" />
                            Learner access
                        </span>
                        <h1 class="access-title">Start your reading path</h1>
                        <p class="access-subtitle">
                            Enter your learner code to open your dashboard, current activity, or next reading check.
                        </p>

                        <div class="access-path-preview">
                            <article class="access-preview-item">
                                <span class="access-preview-star">
                                    <Star class="size-4 fill-current" stroke-width="2.8" />
                                </span>
                                <span>
                                    <span class="access-preview-label">Saved progress</span>
                                    <span class="access-preview-detail">Your next step is restored automatically.</span>
                                </span>
                            </article>
                            <article class="access-preview-item">
                                <span class="access-preview-star access-preview-star--teal">
                                    <ClipboardCheck class="size-4" stroke-width="2.8" />
                                </span>
                                <span>
                                    <span class="access-preview-label">Reading checks</span>
                                    <span class="access-preview-detail">Continue from the right place each time.</span>
                                </span>
                            </article>
                        </div>
                    </div>

                    <form class="access-form-panel" @submit.prevent="submit">
                        <div>
                            <p class="access-form-kicker">Learner code</p>
                            <h2 class="access-form-title">Enter your code</h2>
                        </div>

                        <div class="access-field">
                            <label for="learner_code" class="access-label">Your learner code</label>
                            <input
                                id="learner_code"
                                v-model="form.learner_code"
                                type="text"
                                autocomplete="off"
                                spellcheck="false"
                                :class="['access-input', form.errors.learner_code ? 'access-input--error' : '']"
                                placeholder="RD-0000"
                            >
                            <Transition name="access-error">
                                <p v-if="form.errors.learner_code" class="access-error">
                                    {{ form.errors.learner_code }}
                                </p>
                            </Transition>
                            <p v-if="!form.errors.learner_code" class="access-helper">
                                Ask your teacher if you need your learner code.
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="access-submit rd-submit-button"
                        >
                            <template v-if="form.processing">
                                <Loader2 class="size-5 animate-spin" />
                                <span>Checking</span>
                            </template>
                            <template v-else>
                                <span>Continue</span>
                                <ArrowRight class="size-5" stroke-width="3" />
                            </template>
                        </button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.access-shell {
    min-height: 100vh;
    overflow-x: hidden;
    background:
        url('/images/backgrounds/learner-dashboard-desktop.png'),
        linear-gradient(180deg, #f4e0ba 0%, #faf7ef 100%);
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    color: var(--rd-text-main);
}

.access-topbar {
    position: fixed;
    top: 1rem;
    left: 1rem;
    right: 1rem;
    z-index: 30;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.access-brand,
.access-home-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}

.access-brand {
    gap: 0.6rem;
    border: 2px solid var(--rd-story-border-soft);
    border-radius: 999px;
    background: var(--rd-story-surface);
    padding: 0.35rem 0.95rem 0.35rem 0.35rem;
    color: var(--rd-text-main);
    font-size: 1.05rem;
    font-weight: 900;
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.16), 0 8px 14px rgba(54, 83, 101, 0.12);
}

.access-brand-mark,
.access-preview-star {
    display: grid;
    place-items: center;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 3px 0 #b84b24, 0 7px 12px rgba(245, 133, 73, 0.2);
}

.access-brand-mark {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.85rem;
}

.access-header-actions {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.access-home-link {
    min-height: 2.75rem;
    justify-content: center;
    gap: 0.45rem;
    border: 2px solid var(--rd-story-border-soft);
    border-radius: 999px;
    background: var(--rd-story-surface);
    padding: 0.55rem 0.95rem;
    color: var(--rd-text-main);
    font-size: 0.82rem;
    font-weight: 900;
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.14), 0 8px 14px rgba(54, 83, 101, 0.1);
}

.access-home-link:hover {
    color: var(--rd-primary-orange);
}

.access-main {
    display: grid;
    min-height: 100vh;
    place-items: center;
    padding: 6rem 1rem 2rem;
}

.access-card {
    width: min(100%, 66rem);
}

.access-card-face {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(20rem, 0.85fr);
    gap: clamp(1rem, 3vw, 1.35rem);
    padding: clamp(1rem, 3vw, 1.4rem);
}

.access-copy,
.access-form-panel {
    min-width: 0;
}

.access-copy {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1rem;
    padding: clamp(0.4rem, 2vw, 1rem);
}

.access-badge {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.1);
    padding: 0.4rem 0.75rem;
    color: var(--rd-primary-orange);
    font-size: 0.74rem;
    font-weight: 900;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.access-title {
    max-width: 35rem;
    color: var(--rd-text-main);
    font-size: clamp(2.4rem, 7vw, 4.75rem);
    font-weight: 900;
    letter-spacing: 0;
    line-height: 0.94;
}

.access-subtitle {
    max-width: 35rem;
    color: var(--rd-text-muted);
    font-size: clamp(1rem, 2vw, 1.1rem);
    font-weight: 800;
    line-height: 1.48;
}

.access-path-preview {
    display: grid;
    gap: 0.7rem;
    margin-top: 0.4rem;
}

.access-preview-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1rem;
    background: var(--rd-face-surface);
    padding: 0.8rem;
}

.access-preview-star {
    width: 2.45rem;
    height: 2.45rem;
    flex-shrink: 0;
    border-radius: 999px;
}

.access-preview-star--teal {
    background: var(--rd-depth-blue);
    box-shadow: 0 3px 0 #223849, 0 7px 12px rgba(54, 83, 101, 0.2);
}

.access-preview-label {
    display: block;
    color: var(--rd-text-main);
    font-size: 0.92rem;
    font-weight: 900;
    line-height: 1.1;
}

.access-preview-detail {
    display: block;
    margin-top: 0.15rem;
    color: var(--rd-text-muted);
    font-size: 0.76rem;
    font-weight: 800;
    line-height: 1.35;
}

.access-form-panel {
    display: grid;
    align-content: center;
    gap: 1rem;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1.15rem;
    background: var(--rd-face-surface);
    padding: clamp(1rem, 3vw, 1.35rem);
}

.access-form-kicker,
.access-label {
    color: var(--rd-primary-orange);
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.access-form-title {
    margin-top: 0.2rem;
    color: var(--rd-text-main);
    font-size: clamp(1.45rem, 3.5vw, 2rem);
    font-weight: 900;
    line-height: 1.05;
}

.access-field {
    display: grid;
    gap: 0.5rem;
}

.access-label {
    color: var(--rd-text-muted);
}

.access-input {
    width: 100%;
    border: 2px solid var(--rd-frame-border);
    border-radius: 1rem;
    background: #fff;
    padding: 0.9rem 1rem;
    color: var(--rd-text-main);
    font-size: clamp(1.35rem, 4vw, 1.8rem);
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.access-input::placeholder {
    color: rgba(95, 111, 120, 0.28);
}

.access-input:focus {
    border-color: var(--rd-primary-orange);
    box-shadow: 0 0 0 4px rgba(245, 133, 73, 0.1);
    outline: none;
}

.access-input--error {
    border-color: #dc2626;
    color: #b91c1c;
}

.access-helper {
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 800;
    text-align: center;
}

.access-error {
    border: 1.5px solid rgba(220, 38, 38, 0.24);
    border-radius: 0.85rem;
    background: #fff1f2;
    padding: 0.65rem 0.75rem;
    color: #dc2626;
    font-size: 0.82rem;
    font-weight: 900;
}

.access-submit {
    display: inline-flex;
    min-height: 3.5rem;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.8rem 1.4rem;
    font-size: 0.98rem;
}

.access-error-enter-active,
.access-error-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.access-error-enter-from,
.access-error-leave-to {
    opacity: 0;
    transform: translateY(-0.25rem);
}

@media (max-width: 860px) {
    .access-card-face {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .access-topbar {
        top: 0.75rem;
        left: 0.75rem;
        right: 0.75rem;
    }

    .access-header-actions {
        align-items: flex-end;
        flex-direction: column;
    }

    .access-home-link {
        padding-inline: 0.75rem;
    }
}
</style>
