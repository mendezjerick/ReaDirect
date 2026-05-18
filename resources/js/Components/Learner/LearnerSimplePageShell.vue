<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChevronLeft, ClipboardList, HelpCircle, Home, Menu, Trophy, X } from 'lucide-vue-next';

const props = defineProps({
    learner: { type: Object, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    active: { type: String, default: '' },
});

const sidebarOpen = ref(false);
const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const initial = computed(() => firstName.value.charAt(0).toUpperCase());

const navItems = [
    { key: 'dashboard', label: 'Dashboard', href: '/learner/dashboard', icon: Home },
    { key: 'modules', label: 'My Learning', href: '/learner/modules', icon: BookOpen },
    { key: 'progress', label: 'Progress', href: '/learner/progress', icon: ClipboardList },
    { key: 'rewards', label: 'Rewards', href: '/learner/rewards', icon: Trophy },
    { key: 'help', label: 'Help', href: '/learner/help', icon: HelpCircle },
];
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 text-text lg:flex-row">
        <Transition name="overlay">
            <button
                v-if="sidebarOpen"
                type="button"
                class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm lg:hidden"
                aria-label="Close menu"
                @click="sidebarOpen = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-300 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:w-56 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
                <Link href="/learner/dashboard" class="flex items-center gap-2" @click="sidebarOpen = false">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <BookOpen :size="18" />
                    </span>
                    <span class="text-lg font-black tracking-tight text-primary">ReaDirect</span>
                </Link>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-text lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <X :size="18" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pt-2">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                    :class="active === item.key ? 'bg-blue-50 font-bold text-primary' : 'text-slate-500 hover:bg-slate-50 hover:text-text'"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" :size="16" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="m-3 rounded-2xl bg-linear-to-br from-blue-50 to-blue-100/60 p-3">
                <p class="text-xs font-black uppercase text-primary">Signed in as</p>
                <div class="mt-2 flex items-center gap-2">
                    <span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-black text-white">{{ initial }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-black text-text">{{ firstName }}</p>
                        <p class="truncate text-xs font-bold text-slate-500">{{ learner?.learner_code ?? 'Learner' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 shadow-sm sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="rounded-lg p-2 text-primary transition-colors hover:bg-blue-50 lg:hidden"
                        aria-label="Open menu"
                        @click="sidebarOpen = true"
                    >
                        <Menu :size="20" />
                    </button>
                    <Link href="/learner/dashboard" class="hidden items-center gap-2 rounded-full bg-blue-50 px-3 py-2 text-xs font-black text-primary sm:inline-flex">
                        <ChevronLeft :size="14" />
                        Dashboard
                    </Link>
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-black text-text sm:text-2xl">{{ title }}</h1>
                        <p v-if="subtitle" class="truncate text-xs font-bold text-slate-500 sm:text-sm">{{ subtitle }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-full bg-white py-1 pl-1 pr-3 shadow-sm ring-1 ring-slate-200">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-linear-to-br from-blue-400 to-indigo-500 text-sm font-black text-white">
                        {{ initial }}
                    </div>
                    <span class="hidden text-sm font-bold text-text sm:inline">{{ firstName }}</span>
                </div>
            </header>

            <main class="flex-1 p-3 sm:p-5">
                <slot />
            </main>
        </div>
    </div>
</template>
