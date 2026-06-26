<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ChevronLeft, ClipboardList, HelpCircle, Home, Menu, Trophy, X } from 'lucide-vue-next';
import AsrVisualizationToggle from '../AsrVisualizationToggle.vue';

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
    <div class="flex min-h-screen bg-gradient-to-br from-slate-50 to-orange-50/30 text-slate-800 lg:flex-row">
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
            class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-slate-200/80 bg-white shadow-lg shadow-slate-200/20 transition-transform duration-300 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:w-60 lg:translate-x-0 lg:shadow-none"
            :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
        >
            <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
                <Link href="/learner/dashboard" class="flex items-center gap-2.5" @click="sidebarOpen = false">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary text-white shadow-md shadow-primary/20">
                        <BookOpen :size="18" stroke-width="2.5" />
                    </span>
                    <span class="text-lg font-black tracking-tight text-slate-800">ReaDirect</span>
                </Link>
                <button
                    type="button"
                    class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <X :size="18" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pt-3">
                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition-all duration-200"
                    :class="active === item.key
                        ? 'bg-primary-light font-black text-primary shadow-sm ring-1 ring-primary/20'
                        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700'"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" :size="18" stroke-width="2.5" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="m-3 rounded-[24px] border border-slate-200/60 bg-slate-50/80 p-4 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-widest text-primary">Signed in as</p>
                <div class="mt-2.5 flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-2xl bg-primary text-sm font-black text-white shadow-sm shadow-primary/20">{{ initial }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-black text-slate-800">{{ firstName }}</p>
                        <p class="truncate text-xs font-semibold text-slate-400">{{ learner?.learner_code ?? 'Learner' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-slate-200/60 bg-white/80 px-4 shadow-sm backdrop-blur-md sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl p-2 text-primary transition-colors hover:bg-primary-light lg:hidden"
                        aria-label="Open menu"
                        @click="sidebarOpen = true"
                    >
                        <Menu :size="20" />
                    </button>
                    <Link href="/learner/dashboard" class="hidden items-center gap-2 rounded-full bg-primary-light px-3.5 py-2 text-xs font-black text-primary ring-1 ring-primary/20 transition-all hover:bg-surface sm:inline-flex">
                        <ChevronLeft :size="14" stroke-width="3" />
                        Dashboard
                    </Link>
                    <div class="min-w-0">
                        <h1 class="truncate text-xl font-black text-slate-800 sm:text-2xl">{{ title }}</h1>
                        <p v-if="subtitle" class="truncate text-xs font-semibold text-slate-400 sm:text-sm">{{ subtitle }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <AsrVisualizationToggle />
                    <div class="flex items-center gap-2 rounded-full bg-white py-1 pl-1 pr-3 shadow-sm ring-1 ring-slate-200/80">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-sm font-black text-white">
                            {{ initial }}
                        </div>
                        <span class="hidden text-sm font-bold text-slate-700 sm:inline">{{ firstName }}</span>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 xl:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
