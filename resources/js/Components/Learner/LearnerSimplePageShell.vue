<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    BookOpenCheck,
    ChevronDown,
    ClipboardList,
    HelpCircle,
    Home,
    Menu,
    Trophy,
    UserRound,
    X,
} from 'lucide-vue-next';
import AsrVisualizationToggle from '../AsrVisualizationToggle.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    active: { type: String, default: '' },
    showHeader: { type: Boolean, default: true },
});

const menuOpen = ref(false);
const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const initial = computed(() => firstName.value.charAt(0).toUpperCase());

const navItems = [
    { key: 'dashboard', label: 'Dashboard', href: '/learner/dashboard', icon: Home },
    { key: 'modules', label: 'My Learning', href: '/learner/modules', icon: BookOpen },
    { key: 'progress', label: 'Progress', href: '/learner/progress', icon: ClipboardList },
    { key: 'rewards', label: 'Rewards', href: '/learner/rewards', icon: Trophy },
    { key: 'help', label: 'Help', href: '/learner/help', icon: HelpCircle },
];

const closeMenu = () => {
    menuOpen.value = false;
};

const handleOutsideClick = (event) => {
    if (menuOpen.value && !event.target.closest('.learner-hub-menu')) {
        closeMenu();
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleOutsideClick);
});
</script>

<template>
    <div class="learner-hub-shell">
        <div class="learner-hub-topbar">
            <div class="learner-hub-menu">
                <button
                    type="button"
                    class="learner-hub-menu-button"
                    :aria-label="menuOpen ? 'Close menu' : 'Open menu'"
                    :aria-expanded="menuOpen"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" class="size-5" stroke-width="3" />
                    <Menu v-else class="size-5" stroke-width="3" />
                </button>

                <Transition name="learner-hub-menu-pop">
                    <div v-if="menuOpen" class="learner-hub-menu-panel">
                        <div class="learner-hub-menu-head">
                            <Link href="/learner/dashboard" class="learner-hub-menu-brand" @click="closeMenu">
                                <span class="learner-hub-brand-mark">
                                    <BookOpenCheck class="size-5" stroke-width="2.7" />
                                </span>
                                <span>ReaDirect</span>
                            </Link>
                            <button
                                type="button"
                                class="learner-hub-menu-close"
                                aria-label="Close menu"
                                @click="closeMenu"
                            >
                                <X class="size-4" stroke-width="3" />
                            </button>
                        </div>

                        <nav class="learner-hub-menu-nav" aria-label="Learner navigation">
                            <Link
                                v-for="item in navItems"
                                :key="item.key"
                                :href="item.href"
                                class="learner-hub-menu-link"
                                :class="{ 'learner-hub-menu-link--active': active === item.key }"
                                @click="closeMenu"
                            >
                                <component :is="item.icon" class="size-5" stroke-width="2.6" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </nav>

                        <div class="learner-hub-menu-card">
                            <span class="learner-hub-menu-card-icon">
                                <UserRound class="size-4" stroke-width="2.8" />
                            </span>
                            <span>
                                <span class="learner-hub-menu-card-kicker">Signed in as</span>
                                <span class="learner-hub-menu-card-name">{{ firstName }}</span>
                            </span>
                        </div>
                    </div>
                </Transition>
            </div>

            <div class="learner-hub-profile">
                <AsrVisualizationToggle />
                <div class="learner-hub-user-pill">
                    <span class="learner-hub-user-initial">{{ initial }}</span>
                    <span class="learner-hub-user-name">{{ firstName }}</span>
                    <ChevronDown class="size-4 text-[var(--rd-text-muted)]" stroke-width="3" />
                </div>
            </div>
        </div>

        <main class="learner-hub-main">
            <header v-if="showHeader" class="learner-hub-page-head">
                <p class="learner-hub-kicker">Learner space</p>
                <h1 class="learner-hub-page-title">{{ title }}</h1>
                <p v-if="subtitle" class="learner-hub-page-subtitle">{{ subtitle }}</p>
            </header>

            <slot />
        </main>
    </div>
</template>

<style>
.learner-hub-shell {
    position: relative;
    min-height: 100vh;
    overflow-x: hidden;
    background:
        linear-gradient(180deg, rgba(255, 253, 247, 0), rgba(250, 247, 239, 0)),
        url('/images/backgrounds/learner-dashboard-desktop.png'),
        linear-gradient(180deg, #f4e0ba 0%, #faf7ef 100%);
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    color: var(--rd-text-main);
}

.learner-hub-topbar {
    position: fixed;
    top: 1rem;
    left: 1rem;
    right: 1rem;
    z-index: 50;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    pointer-events: none;
}

.learner-hub-menu,
.learner-hub-profile {
    pointer-events: auto;
}

.learner-hub-menu {
    position: relative;
}

.learner-hub-menu-button,
.learner-hub-menu-close {
    display: grid;
    place-items: center;
    border: 2px solid var(--rd-story-border-soft);
    background: var(--rd-story-surface);
    color: var(--rd-text-main);
    box-shadow:
        0 4px 0 rgba(111, 101, 52, 0.16),
        0 8px 14px rgba(54, 83, 101, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.learner-hub-menu-button {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
}

.learner-hub-menu-button:hover,
.learner-hub-menu-close:hover {
    color: var(--rd-primary-orange);
    transform: translateY(-1px);
}

.learner-hub-menu-close {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 0.75rem;
}

.learner-hub-menu-pop-enter-active,
.learner-hub-menu-pop-leave-active {
    transform-origin: top left;
    transition: opacity 180ms ease, transform 180ms ease;
}

.learner-hub-menu-pop-enter-from,
.learner-hub-menu-pop-leave-to {
    opacity: 0;
    transform: translateY(-0.5rem) scale(0.97);
}

.learner-hub-menu-panel {
    position: absolute;
    top: calc(100% + 0.8rem);
    left: 0;
    width: min(19rem, calc(100vw - 2rem));
    overflow: hidden;
    border: 2px solid var(--rd-story-border);
    border-radius: 1.25rem;
    background: var(--rd-story-surface);
    box-shadow:
        0 5px 0 var(--rd-lip),
        0 7px 0 var(--rd-lip-dark),
        0 18px 28px -10px var(--rd-shadow);
}

.learner-hub-menu-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1.5px solid var(--rd-face-border);
    background: var(--rd-face-surface);
    padding: 0.8rem;
}

.learner-hub-menu-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    color: var(--rd-text-main);
    font-size: 1rem;
    font-weight: 900;
    text-decoration: none;
}

.learner-hub-brand-mark,
.learner-hub-user-initial,
.learner-hub-menu-card-icon {
    display: grid;
    place-items: center;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 3px 0 #b84b24, 0 7px 12px rgba(245, 133, 73, 0.2);
}

.learner-hub-brand-mark {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.85rem;
}

.learner-hub-menu-nav {
    display: grid;
    gap: 0.45rem;
    padding: 0.8rem;
}

.learner-hub-menu-link {
    display: flex;
    min-height: 3rem;
    align-items: center;
    gap: 0.75rem;
    border: 1.5px solid transparent;
    border-radius: 0.95rem;
    padding: 0.65rem 0.85rem;
    color: var(--rd-text-muted);
    font-size: 0.88rem;
    font-weight: 900;
    text-decoration: none;
}

.learner-hub-menu-link:hover,
.learner-hub-menu-link--active {
    border-color: rgba(245, 133, 73, 0.28);
    background: rgba(245, 133, 73, 0.1);
    color: var(--rd-primary-orange);
}

.learner-hub-menu-card {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin: 0 0.8rem 0.8rem;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1rem;
    background: var(--rd-face-surface);
    padding: 0.75rem;
}

.learner-hub-menu-card-icon {
    width: 2.2rem;
    height: 2.2rem;
    flex-shrink: 0;
    border-radius: 0.75rem;
}

.learner-hub-menu-card-kicker,
.learner-hub-kicker {
    display: block;
    color: var(--rd-primary-orange);
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.learner-hub-menu-card-name {
    display: block;
    margin-top: 0.1rem;
    color: var(--rd-text-main);
    font-size: 0.9rem;
    font-weight: 900;
}

.learner-hub-profile {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.learner-hub-user-pill {
    display: inline-flex;
    min-height: 3rem;
    align-items: center;
    gap: 0.55rem;
    border: 2px solid var(--rd-story-border-soft);
    border-radius: 999px;
    background: var(--rd-story-surface);
    padding: 0.35rem 0.85rem 0.35rem 0.35rem;
    box-shadow:
        0 4px 0 rgba(111, 101, 52, 0.16),
        0 8px 14px rgba(54, 83, 101, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.learner-hub-user-initial {
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    font-size: 0.86rem;
    font-weight: 900;
}

.learner-hub-user-name {
    max-width: 8rem;
    overflow: hidden;
    color: var(--rd-text-main);
    font-size: 0.88rem;
    font-weight: 900;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.learner-hub-main {
    width: min(100% - 2rem, 74rem);
    margin: 0 auto;
    padding: 5.9rem 0 3rem;
}

.learner-hub-page-head {
    margin-bottom: 1.2rem;
}

.learner-hub-page-title {
    margin-top: 0.25rem;
    color: var(--rd-text-main);
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 900;
    letter-spacing: 0;
    line-height: 1.02;
}

.learner-hub-page-subtitle {
    margin-top: 0.35rem;
    max-width: 42rem;
    color: var(--rd-text-muted);
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.45;
}

.learner-hub-panel,
.learner-hub-card {
    border: 2px solid var(--rd-story-border);
    background: var(--rd-story-surface);
    box-shadow:
        0 5px 0 var(--rd-lip),
        0 7px 0 var(--rd-lip-dark),
        0 16px 22px -8px var(--rd-shadow);
}

.learner-hub-panel {
    border-radius: 1.35rem;
    padding: clamp(1rem, 3vw, 1.45rem);
}

.learner-hub-card {
    border-radius: 1.15rem;
    padding: 1rem;
}

.learner-hub-face {
    border: 1.5px solid var(--rd-face-border);
    border-radius: 1rem;
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.learner-hub-section-title {
    color: var(--rd-text-main);
    font-size: clamp(1.25rem, 2.4vw, 1.7rem);
    font-weight: 900;
    line-height: 1.12;
}

.learner-hub-section-copy {
    color: var(--rd-text-muted);
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.45;
}

.learner-hub-primary-link,
.learner-hub-secondary-link {
    display: inline-flex;
    min-height: 3.4rem;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    border-radius: 999px;
    padding: 0.75rem 1.35rem;
    font-size: 0.92rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-decoration: none;
    text-transform: uppercase;
    white-space: nowrap;
}

.learner-hub-primary-link {
    border: 2px solid #d9652f;
    background: linear-gradient(180deg, var(--rd-action-button-light) 0%, var(--rd-action-button) 100%);
    color: #fff;
    box-shadow:
        0 7px 0 #b84b24,
        0 12px 20px rgba(54, 83, 101, 0.22),
        inset 0 2px 0 rgba(255, 255, 255, 0.35);
}

.learner-hub-primary-link:hover {
    background: linear-gradient(180deg, var(--rd-action-button-light) 0%, var(--rd-action-button) 100%);
    color: #fff;
    transform: translateY(-1px);
}

.learner-hub-primary-link:active {
    transform: translateY(5px);
    box-shadow: 0 2px 0 #b84b24, 0 6px 12px rgba(54, 83, 101, 0.2);
}

.learner-hub-secondary-link {
    border: 2px solid rgba(54, 83, 101, 0.14);
    background: var(--rd-face-surface);
    color: var(--rd-text-main);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.12);
}

.learner-hub-secondary-link:hover {
    border-color: rgba(245, 133, 73, 0.36);
    color: var(--rd-primary-orange);
}

.learner-hub-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.1);
    padding: 0.4rem 0.75rem;
    color: var(--rd-primary-orange);
    font-size: 0.74rem;
    font-weight: 900;
    line-height: 1;
}

.learner-hub-grid {
    display: grid;
    gap: 1rem;
}

@media (max-width: 640px) {
    .learner-hub-main {
        width: min(100% - 1.25rem, 74rem);
        padding-top: 5.4rem;
    }

    .learner-hub-topbar {
        top: 0.75rem;
        left: 0.75rem;
        right: 0.75rem;
    }

    .learner-hub-user-name {
        display: none;
    }
}
</style>
