<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, BookOpenCheck, Home, Loader2, Lock, Mail, ShieldCheck, Star, UserRound } from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => form.post('/login');
</script>

<template>
    <div class="login-shell">
        <header class="login-topbar">
            <Link href="/" class="login-brand" aria-label="Back to ReaDirect home">
                <span class="login-brand-mark">
                    <BookOpenCheck class="size-5" stroke-width="2.8" />
                </span>
                <span>ReaDirect</span>
            </Link>

            <Link href="/" class="login-home-link">
                <Home class="size-4" stroke-width="3" />
                Home
            </Link>
        </header>

        <main class="login-main">
            <section class="login-card rd-card">
                <div class="login-card-face rd-card__face">
                    <div class="login-copy">
                        <span class="login-badge">
                            <ShieldCheck class="size-4" stroke-width="2.8" />
                            Staff access
                        </span>
                        <h1 class="login-title">Sign in to ReaDirect</h1>
                        <p class="login-subtitle">
                            Open the teacher and admin workspace for learner management, reports, and QA tools.
                        </p>

                        <div class="login-mini-path">
                            <article class="login-mini-item">
                                <span class="login-mini-icon">
                                    <UserRound class="size-4" stroke-width="2.8" />
                                </span>
                                <span>Teacher dashboard</span>
                            </article>
                            <article class="login-mini-item">
                                <span class="login-mini-icon login-mini-icon--gold">
                                    <Star class="size-4 fill-current" stroke-width="2.8" />
                                </span>
                                <span>Learner progress</span>
                            </article>
                        </div>
                    </div>

                    <form class="login-form-panel" @submit.prevent="submit">
                        <div>
                            <p class="login-form-kicker">Secure login</p>
                            <h2 class="login-form-title">Welcome back</h2>
                        </div>

                        <div class="login-field">
                            <label for="email" class="login-label">Email</label>
                            <div class="login-input-wrap" :class="{ 'login-input-wrap--error': form.errors.email }">
                                <Mail class="size-5" stroke-width="2.6" />
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    class="login-input"
                                >
                            </div>
                            <p v-if="form.errors.email" class="login-error">{{ form.errors.email }}</p>
                        </div>

                        <div class="login-field">
                            <label for="password" class="login-label">Password</label>
                            <div class="login-input-wrap" :class="{ 'login-input-wrap--error': form.errors.password }">
                                <Lock class="size-5" stroke-width="2.6" />
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="current-password"
                                    class="login-input"
                                >
                            </div>
                            <p v-if="form.errors.password" class="login-error">{{ form.errors.password }}</p>
                        </div>

                        <label class="login-remember">
                            <input v-model="form.remember" type="checkbox">
                            <span>Keep me signed in</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="login-submit rd-submit-button"
                        >
                            <template v-if="form.processing">
                                <Loader2 class="size-5 animate-spin" />
                                <span>Signing in</span>
                            </template>
                            <template v-else>
                                <span>Sign In</span>
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
.login-shell {
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

.login-topbar {
    position: fixed;
    top: 1rem;
    left: 1rem;
    right: 1rem;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.login-brand,
.login-home-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}

.login-brand {
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

.login-brand-mark,
.login-mini-icon {
    display: grid;
    place-items: center;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 3px 0 #b84b24, 0 7px 12px rgba(245, 133, 73, 0.2);
}

.login-brand-mark {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.85rem;
}

.login-home-link {
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

.login-home-link:hover {
    color: var(--rd-primary-orange);
}

.login-main {
    display: grid;
    min-height: 100vh;
    place-items: center;
    padding: 6rem 1rem 2rem;
}

.login-card {
    width: min(100%, 64rem);
}

.login-card-face {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(20rem, 0.85fr);
    gap: clamp(1rem, 3vw, 1.35rem);
    padding: clamp(1rem, 3vw, 1.4rem);
}

.login-copy,
.login-form-panel {
    min-width: 0;
}

.login-copy {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1rem;
    padding: clamp(0.4rem, 2vw, 1rem);
}

.login-badge {
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

.login-title {
    max-width: 35rem;
    color: var(--rd-text-main);
    font-size: clamp(2.35rem, 7vw, 4.6rem);
    font-weight: 900;
    letter-spacing: 0;
    line-height: 0.94;
}

.login-subtitle {
    max-width: 35rem;
    color: var(--rd-text-muted);
    font-size: clamp(1rem, 2vw, 1.1rem);
    font-weight: 800;
    line-height: 1.48;
}

.login-mini-path {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
    margin-top: 0.4rem;
}

.login-mini-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1rem;
    background: var(--rd-face-surface);
    padding: 0.75rem;
    color: var(--rd-text-main);
    font-size: 0.82rem;
    font-weight: 900;
}

.login-mini-icon {
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 0.75rem;
}

.login-mini-icon--gold {
    background: #b45309;
    box-shadow: 0 3px 0 #78350f, 0 7px 12px rgba(180, 83, 9, 0.2);
}

.login-form-panel {
    display: grid;
    align-content: center;
    gap: 1rem;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1.15rem;
    background: var(--rd-face-surface);
    padding: clamp(1rem, 3vw, 1.35rem);
}

.login-form-kicker,
.login-label {
    color: var(--rd-primary-orange);
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.login-form-title {
    margin-top: 0.2rem;
    color: var(--rd-text-main);
    font-size: clamp(1.45rem, 3.5vw, 2rem);
    font-weight: 900;
    line-height: 1.05;
}

.login-field {
    display: grid;
    gap: 0.5rem;
}

.login-label {
    color: var(--rd-text-muted);
}

.login-input-wrap {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    border: 2px solid var(--rd-frame-border);
    border-radius: 1rem;
    background: #fff;
    padding: 0.8rem 0.95rem;
    color: var(--rd-text-muted);
}

.login-input-wrap:focus-within {
    border-color: var(--rd-primary-orange);
    box-shadow: 0 0 0 4px rgba(245, 133, 73, 0.1);
}

.login-input-wrap--error {
    border-color: #dc2626;
}

.login-input {
    min-width: 0;
    flex: 1;
    border: 0;
    background: transparent;
    color: var(--rd-text-main);
    font-size: 1rem;
    font-weight: 800;
    outline: none;
}

.login-error {
    border: 1.5px solid rgba(220, 38, 38, 0.24);
    border-radius: 0.85rem;
    background: #fff1f2;
    padding: 0.6rem 0.75rem;
    color: #dc2626;
    font-size: 0.8rem;
    font-weight: 900;
}

.login-remember {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    color: var(--rd-text-muted);
    font-size: 0.84rem;
    font-weight: 900;
}

.login-remember input {
    width: 1rem;
    height: 1rem;
    accent-color: var(--rd-primary-orange);
}

.login-submit {
    display: inline-flex;
    min-height: 3.5rem;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.8rem 1.4rem;
    font-size: 0.98rem;
}

@media (max-width: 860px) {
    .login-card-face {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .login-topbar {
        top: 0.75rem;
        left: 0.75rem;
        right: 0.75rem;
    }

    .login-mini-path {
        grid-template-columns: 1fr;
    }
}
</style>
