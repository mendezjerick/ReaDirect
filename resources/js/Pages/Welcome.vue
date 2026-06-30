<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    ArrowRight,
    BookOpen,
    BookOpenCheck,
    ClipboardCheck,
    LogIn,
    Mic2,
    ShieldCheck,
    Star,
    Trophy,
} from 'lucide-vue-next';
import ReaDirectBookLoader from '../Components/Loading/ReaDirectBookLoader.vue';
import {
    preloadAgentMediaForRoute,
    scheduleAgentMediaPreload,
} from '../utils/agentMediaPreloader';

const page = usePage();
const roles = computed(() => page.props.auth?.roles ?? []);
const isLoggedIn = computed(() => Boolean(page.props.auth?.user));
const dashboardLink = computed(() => {
    if (roles.value.includes('system_admin') || roles.value.includes('school_admin')) {
        return { href: '/admin/dashboard', label: 'Admin dashboard' };
    }

    if (roles.value.includes('teacher')) {
        return { href: '/teacher/dashboard', label: 'Teacher dashboard' };
    }

    return { href: '/learner/dashboard', label: 'Learner dashboard' };
});
const preparingAgents = ref(false);

const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
const enterPreloadTimeoutMs = 2500;

onMounted(() => {
    scheduleAgentMediaPreload('welcome', {
        batchSize: 2,
        timeoutMs: 6000,
    });
});

const enterApp = async (event, href) => {
    event?.preventDefault();
    if (preparingAgents.value) return;

    preparingAgents.value = true;
    await Promise.race([
        preloadAgentMediaForRoute(href, {
            batchSize: 2,
            timeoutMs: 7000,
        }),
        wait(enterPreloadTimeoutMs),
    ]);

    scheduleAgentMediaPreload('all', {
        batchSize: 2,
        delayMs: 1200,
        idleTimeoutMs: 3000,
        timeoutMs: 10000,
    });

    router.visit(href);
};

const pathItems = [
    { label: 'Check', detail: 'Start with a reading check.', icon: ClipboardCheck, tone: 'orange' },
    { label: 'Practice', detail: 'Continue with your lesson path.', icon: BookOpen, tone: 'teal' },
    { label: 'Grow', detail: 'Earn stars and see progress.', icon: Trophy, tone: 'gold' },
];
</script>

<template>
    <div class="home-entry-shell">
        <ReaDirectBookLoader v-if="preparingAgents" />

        <header class="home-topbar">
            <a href="/" class="home-brand" aria-label="ReaDirect home">
                <span class="home-brand-mark">
                    <BookOpenCheck class="size-5" stroke-width="2.8" />
                </span>
                <span>ReaDirect</span>
            </a>

            <nav class="home-nav" aria-label="Home navigation">
                <Link
                    v-if="!isLoggedIn"
                    href="/login"
                    class="home-nav-link"
                >
                    <LogIn class="size-4" stroke-width="2.8" />
                    Login
                </Link>
                <template v-else>
                    <a
                        :href="dashboardLink.href"
                        class="home-nav-link"
                        @click="enterApp($event, dashboardLink.href)"
                    >
                        {{ dashboardLink.label }}
                    </a>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="home-nav-link home-nav-link--filled"
                    >
                        Logout
                    </Link>
                </template>
            </nav>
        </header>

        <main class="home-main">
            <section class="home-card rd-card">
                <div class="home-card-face rd-card__face">
                    <div class="home-copy">
                        <span class="home-badge">
                            <ShieldCheck class="size-4" stroke-width="2.8" />
                            Grade 1 reading practice
                        </span>
                        <h1 class="home-title">ReaDirect</h1>
                        <p class="home-subtitle">
                            A focused reading space for checks, guided practice, progress, and completion.
                        </p>

                        <div class="home-actions">
                            <a
                                href="/learner/access"
                                class="home-primary"
                                @click="enterApp($event, '/learner/access')"
                            >
                                Start Reading
                                <ArrowRight class="size-5" stroke-width="3" />
                            </a>
                            <a
                                v-if="isLoggedIn"
                                :href="dashboardLink.href"
                                class="home-secondary"
                                @click="enterApp($event, dashboardLink.href)"
                            >
                                {{ dashboardLink.label }}
                            </a>
                            <Link
                                v-else
                                href="/login"
                                class="home-secondary"
                            >
                                Staff Login
                            </Link>
                        </div>
                    </div>

                    <div class="home-path-panel">
                        <div class="home-path-head">
                            <span class="home-path-star">
                                <Star class="size-5 fill-current" stroke-width="2.8" />
                            </span>
                            <div>
                                <p class="home-kicker">Reading path</p>
                                <h2 class="home-path-title">Start, practice, finish</h2>
                            </div>
                        </div>

                        <div class="home-path-list">
                            <article
                                v-for="item in pathItems"
                                :key="item.label"
                                class="home-path-item"
                                :class="`home-path-item--${item.tone}`"
                            >
                                <span class="home-path-icon">
                                    <component :is="item.icon" class="size-5" stroke-width="2.8" />
                                </span>
                                <span>
                                    <span class="home-path-label">{{ item.label }}</span>
                                    <span class="home-path-detail">{{ item.detail }}</span>
                                </span>
                            </article>
                        </div>

                        <div class="home-recording-note">
                            <Mic2 class="size-5" stroke-width="2.8" />
                            <span>Voice reading tasks stay inside the learner flow.</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.home-entry-shell {
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

.home-topbar {
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

.home-brand,
.home-nav-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}

.home-brand {
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

.home-brand-mark,
.home-path-star {
    display: grid;
    place-items: center;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 3px 0 #b84b24, 0 7px 12px rgba(245, 133, 73, 0.2);
}

.home-brand-mark {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.85rem;
}

.home-nav {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.home-nav-link {
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

.home-nav-link:hover {
    color: var(--rd-primary-orange);
}

.home-nav-link--filled {
    border-color: #d9652f;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
}

.home-main {
    display: grid;
    min-height: 100vh;
    place-items: center;
    padding: 6rem 1rem 2rem;
}

.home-card {
    width: min(100%, 74rem);
}

.home-card-face {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(19rem, 0.95fr);
    gap: clamp(1rem, 3vw, 1.4rem);
    align-items: stretch;
    padding: clamp(1rem, 3vw, 1.4rem);
}

.home-copy,
.home-path-panel {
    min-width: 0;
}

.home-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 1rem;
    padding: clamp(0.4rem, 2vw, 1rem);
}

.home-badge,
.home-recording-note {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.1);
    color: var(--rd-primary-orange);
    font-weight: 900;
}

.home-badge {
    padding: 0.4rem 0.75rem;
    font-size: 0.74rem;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.home-title {
    color: var(--rd-text-main);
    font-size: clamp(3.3rem, 10vw, 6.8rem);
    font-weight: 900;
    letter-spacing: 0;
    line-height: 0.88;
}

.home-subtitle {
    max-width: 38rem;
    color: var(--rd-text-muted);
    font-size: clamp(1rem, 2vw, 1.18rem);
    font-weight: 800;
    line-height: 1.48;
}

.home-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.3rem;
}

.home-primary,
.home-secondary {
    display: inline-flex;
    min-height: 3.5rem;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    border-radius: 999px;
    padding: 0.75rem 1.4rem;
    font-size: 0.92rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-decoration: none;
    text-transform: uppercase;
}

.home-primary {
    border: 2px solid #d9652f;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 7px 0 #b84b24, 0 12px 20px rgba(54, 83, 101, 0.22), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}

.home-primary:hover {
    color: #fff;
    transform: translateY(-1px);
}

.home-primary:active {
    transform: translateY(5px);
    box-shadow: 0 2px 0 #b84b24, 0 6px 12px rgba(54, 83, 101, 0.2);
}

.home-secondary {
    border: 2px solid rgba(54, 83, 101, 0.14);
    background: var(--rd-face-surface);
    color: var(--rd-text-main);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.12);
}

.home-secondary:hover {
    border-color: rgba(245, 133, 73, 0.36);
    color: var(--rd-primary-orange);
}

.home-path-panel {
    display: grid;
    align-content: center;
    gap: 1rem;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1.15rem;
    background: var(--rd-face-surface);
    padding: clamp(1rem, 3vw, 1.25rem);
}

.home-path-head {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.home-path-star {
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    border-radius: 999px;
}

.home-kicker {
    color: var(--rd-primary-orange);
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.home-path-title {
    color: var(--rd-text-main);
    font-size: clamp(1.35rem, 3vw, 1.85rem);
    font-weight: 900;
    line-height: 1.08;
}

.home-path-list {
    display: grid;
    gap: 0.7rem;
}

.home-path-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
    border: 1.5px solid rgba(245, 133, 73, 0.18);
    border-radius: 1rem;
    background: rgba(245, 133, 73, 0.07);
    padding: 0.85rem;
}

.home-path-item--teal {
    border-color: rgba(54, 83, 101, 0.18);
    background: rgba(54, 83, 101, 0.07);
}

.home-path-item--gold {
    border-color: rgba(238, 193, 112, 0.38);
    background: rgba(238, 193, 112, 0.14);
}

.home-path-icon {
    display: grid;
    width: 2.5rem;
    height: 2.5rem;
    place-items: center;
    border-radius: 0.8rem;
    background: var(--rd-face-surface);
    color: var(--rd-primary-orange);
}

.home-path-item--teal .home-path-icon {
    color: var(--rd-depth-blue);
}

.home-path-item--gold .home-path-icon {
    color: #b45309;
}

.home-path-label {
    display: block;
    color: var(--rd-text-main);
    font-size: 0.98rem;
    font-weight: 900;
    line-height: 1.1;
}

.home-path-detail {
    display: block;
    margin-top: 0.18rem;
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.35;
}

.home-recording-note {
    border: 1.5px solid rgba(245, 133, 73, 0.2);
    padding: 0.8rem 0.9rem;
    font-size: 0.84rem;
    line-height: 1.3;
}

@media (max-width: 900px) {
    .home-card-face {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .home-topbar {
        top: 0.75rem;
        left: 0.75rem;
        right: 0.75rem;
    }

    .home-brand {
        padding-right: 0.75rem;
        font-size: 0.96rem;
    }

    .home-nav-link {
        padding-inline: 0.75rem;
    }

    .home-actions,
    .home-actions > * {
        width: 100%;
    }
}
</style>
