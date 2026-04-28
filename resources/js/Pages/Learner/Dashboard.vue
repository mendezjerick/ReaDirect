<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Home,
    BookOpen,
    Trophy,
    HelpCircle,
    ClipboardList,
    Bell,
    ChevronDown,
    Eye,
    BarChart3,
    Lock,
    ArrowRight,
    Sparkles,
    MessageCircle,
    Type,
    BookMarked,
    Menu,
    X,
    Hand,
    GraduationCap,
    Star,
    Rocket,
    Smile,
    Sprout,
} from 'lucide-vue-next';

const props = defineProps({
    learner:       { type: Object, default: null },
    modules:       { type: Array,  default: () => [] },
    latestAttempt: { type: Object, default: null },
});

/* ── Mobile drawer state ─────────────────────── */
const sidebarOpen = ref(false);

/* ── Module display metadata (overrides DB title to user-spec names) ── */
const moduleMeta = {
    module_1: {
        title: 'Letter and Sound Learning',
        blurb: 'Learn the sounds that letters make and how to say them.',
    },
    module_2: {
        title: 'Word Recognition',
        blurb: 'Practice reading and recognizing words quickly and clearly.',
    },
    module_3: {
        title: 'Reading Comprehension',
        blurb: 'Read short passages and answer questions about them.',
    },
};
const metaFor = (key) => moduleMeta[key] ?? { title: 'Module', blurb: '' };

/* ── Identity & state ────────────────────────── */
const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const initial   = computed(() => firstName.value.charAt(0).toUpperCase());

const isDone = computed(() =>
    !!props.latestAttempt && props.latestAttempt.task_1_score != null
);

const assignedKey = computed(() => props.learner?.current_module?.key ?? null);
const assignedTitle = computed(() => {
    if (assignedKey.value) return metaFor(assignedKey.value).title;
    return props.learner?.current_module?.title ?? 'Take the diagnostic';
});

/* ── Score helpers (each task is /10) ─────────── */
const lettersScore   = computed(() => Number(props.latestAttempt?.task_1_score ?? 0));
const sentencesScore = computed(() => Number(
    props.latestAttempt?.task_2b_score ?? props.latestAttempt?.task_2a_score ?? 0
));
const passageScore   = computed(() => {
    const acc = props.latestAttempt?.reading_accuracy;
    if (acc == null) return 0;
    const num = Number(acc);
    return Math.round((num <= 1 ? num : num / 100) * 10);
});

const overallScore = computed(() => {
    if (!isDone.value) return 0;
    const total = lettersScore.value + sentencesScore.value + passageScore.value;
    return Math.round((total / 30) * 100);
});
const overallLabel = computed(() => {
    if (!isDone.value)        return 'Get started!';
    if (overallScore.value >= 70) return 'Great Job!';
    if (overallScore.value >= 40) return 'Good Job!';
    return 'Keep Practicing!';
});

const scoreStatus = (score) => {
    const pct = (score / 10) * 100;
    return pct >= 70 ? 'Good' : 'Keep Practicing';
};

/* ── Pre-computed result cards ─────────────────── */
const results = computed(() => [
    {
        title:    'Letters',
        blurb:    'Identifying letter names and sounds.',
        score:    lettersScore.value,
        icon:     Type,
        bg:       'bg-emerald-50/70',
        border:   'border-emerald-200',
        iconBg:   'bg-emerald-100',
        iconText: 'text-emerald-600',
        titleClr: 'text-emerald-700',
        scoreClr: 'text-emerald-600',
        track:    'bg-emerald-100',
        fill:     'bg-emerald-500',
        pillBg:   'bg-emerald-100',
        pillTxt:  'text-emerald-700',
    },
    {
        title:    'Sentences',
        blurb:    'Reading and understanding simple sentences.',
        score:    sentencesScore.value,
        icon:     MessageCircle,
        bg:       'bg-orange-50/70',
        border:   'border-orange-200',
        iconBg:   'bg-orange-100',
        iconText: 'text-orange-600',
        titleClr: 'text-orange-700',
        scoreClr: 'text-orange-600',
        track:    'bg-orange-100',
        fill:     'bg-orange-500',
        pillBg:   'bg-orange-100',
        pillTxt:  'text-orange-700',
    },
    {
        title:    'Reading Passage',
        blurb:    'Reading short passages and answering questions.',
        score:    passageScore.value,
        icon:     BookMarked,
        bg:       'bg-violet-50/70',
        border:   'border-violet-200',
        iconBg:   'bg-violet-100',
        iconText: 'text-violet-600',
        titleClr: 'text-violet-700',
        scoreClr: 'text-violet-600',
        track:    'bg-violet-100',
        fill:     'bg-violet-500',
        pillBg:   'bg-violet-100',
        pillTxt:  'text-violet-700',
    },
]);

/* ── Module card data ──────────────────────────── */
const moduleCards = computed(() =>
    props.modules.map((m) => {
        const meta = metaFor(m.key);
        const assigned = isDone.value && m.key === assignedKey.value;
        return {
            key:      m.key,
            sequence: m.sequence,
            title:    meta.title,
            blurb:    meta.blurb,
            assigned,
            locked:   !assigned,
        };
    })
);
</script>

<template>
    <!-- ═════════════════════════════════════════════════
         Responsive learner dashboard.
         • Mobile (<lg)  → may scroll, sidebar is a drawer
         • Desktop (lg+) → fixed viewport, no scroll
         ═════════════════════════════════════════════════ -->
    <div class="flex min-h-screen flex-col bg-slate-50 font-sans text-text lg:flex-row">

        <!-- ════════ Mobile overlay (when drawer open) ════════ -->
        <Transition name="overlay">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- ════════ SIDEBAR ════════ -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-300 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:w-55 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
        >
            <!-- Brand -->
            <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <BookOpen :size="18" />
                    </div>
                    <span class="text-lg font-black tracking-tight text-primary">ReaDirect</span>
                </div>
                <button
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-text lg:hidden"
                    aria-label="Close menu"
                    @click="sidebarOpen = false"
                >
                    <X :size="18" />
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pt-2">
                <a
                    href="/learner/dashboard"
                    class="flex items-center gap-3 rounded-xl bg-blue-50 px-3 py-2.5 text-sm font-bold text-primary"
                    @click="sidebarOpen = false"
                >
                    <Home :size="16" />
                    <span>Dashboard</span>
                </a>
                <a
                    href="/learner/modules"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-text"
                    @click="sidebarOpen = false"
                >
                    <BookOpen :size="16" />
                    <span>My Learning</span>
                </a>
                <a
                    href="#"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-text"
                >
                    <ClipboardList :size="16" />
                    <span>Progress</span>
                </a>
                <a
                    href="#"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-text"
                >
                    <Trophy :size="16" />
                    <span>Rewards</span>
                </a>
                <a
                    href="#"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 hover:text-text"
                >
                    <HelpCircle :size="16" />
                    <span>Help</span>
                </a>
            </nav>

            <!-- Mascot card at bottom -->
            <div class="m-3 rounded-2xl bg-linear-to-br from-blue-50 to-blue-100/60 p-3 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-primary text-white shadow-md shadow-primary/30">
                    <GraduationCap :size="20" />
                </div>
                <p class="mt-2 text-[11px] font-bold leading-tight text-primary">
                    Keep learning,<br />keep growing!
                </p>
            </div>
        </aside>

        <!-- ════════ MAIN ════════ -->
        <div class="flex flex-1 flex-col">

            <!-- Top bar -->
            <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-3 bg-primary px-4 shadow-sm sm:px-6">
                <!-- Mobile hamburger -->
                <button
                    class="rounded-lg p-2 text-white transition-colors hover:bg-white/15 lg:hidden"
                    aria-label="Open menu"
                    @click="sidebarOpen = true"
                >
                    <Menu :size="20" />
                </button>

                <!-- Spacer (right-align controls on desktop) -->
                <div class="hidden flex-1 lg:block" />

                <!-- Right side controls -->
                <div class="flex items-center gap-3">
                    <button class="relative rounded-full p-2 text-white transition-colors hover:bg-white/15">
                        <Bell :size="18" />
                        <span class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-black text-white">3</span>
                    </button>
                    <div class="flex items-center gap-2 rounded-full bg-white py-1 pl-1 pr-3 shadow-sm">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-linear-to-br from-blue-400 to-indigo-500 text-sm font-black text-white">
                            {{ initial }}
                        </div>
                        <span class="hidden text-sm font-bold text-text sm:inline">{{ firstName }}</span>
                        <ChevronDown :size="14" class="hidden text-slate-400 sm:block" />
                    </div>
                </div>
            </header>

            <!-- Content (scrollable, natural flow) -->
            <main class="flex flex-1 flex-col gap-3 p-3 sm:p-4 xl:gap-4">

                <!-- ──── HERO ──── -->
                <section class="relative shrink-0 overflow-hidden rounded-2xl bg-linear-to-br from-primary via-blue-500 to-blue-600 p-4 text-white shadow-lg shadow-primary/20 sm:p-5">
                    <!-- Decorative blob -->
                    <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-white/10" />
                    <div class="pointer-events-none absolute -bottom-8 right-32 h-24 w-24 rounded-full bg-white/5" />

                    <div class="relative grid items-center gap-4 lg:grid-cols-[1fr_auto_auto]">

                        <!-- Greeting -->
                        <div class="min-w-0">
                            <h1 class="flex flex-wrap items-center gap-2 text-xl font-black sm:text-2xl lg:text-3xl">
                                Hi, {{ firstName }}!
                                <Hand :size="22" class="text-yellow-200" />
                            </h1>
                            <p v-if="isDone" class="mt-1 text-xs font-semibold text-white/90 sm:text-sm">
                                You did great on your diagnostic test!
                            </p>
                            <p v-else class="mt-1 text-xs font-semibold text-white/90 sm:text-sm">
                                Let's start your reading journey today.
                            </p>
                            <p class="text-xs font-semibold text-white/90 sm:text-sm">
                                <template v-if="isDone">Your learning path is ready. Let's continue your reading journey!</template>
                                <template v-else>Begin with your diagnostic test to find your reading path.</template>
                            </p>
                            <Link
                                v-if="!isDone"
                                href="/learner/diagnostic"
                                class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-black text-primary shadow-md hover:bg-blue-50"
                            >
                                <Sparkles :size="14" />
                                Start my reading check
                                <ArrowRight :size="14" />
                            </Link>
                        </div>

                        <!-- Trophy (hidden on smaller screens to save space) -->
                        <div class="hidden xl:flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 backdrop-blur-sm">
                            <Trophy :size="32" class="text-yellow-200" />
                        </div>

                        <!-- Stat tiles (responsive) -->
                        <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:flex lg:flex-nowrap">
                            <div class="rounded-2xl bg-white/15 p-3 ring-1 ring-white/20 backdrop-blur-sm lg:min-w-32.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-white/70 sm:text-[10px]">Overall Score</p>
                                <p class="mt-1 text-xl font-black leading-none sm:text-2xl">{{ overallScore }}%</p>
                                <p class="mt-1 text-[10px] font-semibold text-white/85 sm:text-[11px]">{{ overallLabel }}</p>
                            </div>
                            <div class="rounded-2xl bg-white/15 p-3 ring-1 ring-white/20 backdrop-blur-sm lg:min-w-42.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-white/70 sm:text-[10px]">Assigned Module</p>
                                <p class="mt-1 truncate text-xs font-black leading-tight sm:text-sm">{{ assignedTitle }}</p>
                                <p class="mt-1 text-[10px] font-semibold text-white/85 sm:text-[11px]">Your current learning path</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ──── DIAGNOSTIC RESULTS ──── -->
                <section class="shrink-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 text-sm font-black text-text sm:text-base">
                            <BarChart3 :size="18" class="text-primary" />
                            Diagnostic Results
                        </h2>
                        <Link
                            href="/learner/diagnostic/reading-summary"
                            class="flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-primary transition-colors hover:bg-blue-100"
                        >
                            <Eye :size="13" />
                            <span class="hidden sm:inline">View Full Result</span>
                            <span class="sm:hidden">Full Result</span>
                        </Link>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-3 sm:gap-3">
                        <article
                            v-for="r in results"
                            :key="r.title"
                            :class="[
                                'rounded-2xl border p-3 transition-all duration-200 hover:-translate-y-0.5 sm:p-3.5',
                                r.bg, r.border,
                            ]"
                        >
                            <div class="flex items-start gap-2.5 sm:gap-3">
                                <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-sm sm:h-10 sm:w-10', r.iconBg, r.iconText]">
                                    <component :is="r.icon" :size="18" />
                                </div>
                                <div class="min-w-0">
                                    <h3 :class="['text-sm font-black leading-tight', r.titleClr]">{{ r.title }}</h3>
                                    <p class="mt-0.5 line-clamp-2 text-[11px] font-semibold leading-snug text-slate-600">{{ r.blurb }}</p>
                                </div>
                            </div>

                            <p class="mt-2.5">
                                <span :class="['text-xl font-black sm:text-2xl', r.scoreClr]">{{ r.score }}</span>
                                <span class="text-sm font-bold text-slate-400"> / 10</span>
                            </p>

                            <div :class="['mt-1.5 h-2 w-full overflow-hidden rounded-full', r.track]">
                                <div
                                    :class="['h-full rounded-full transition-all duration-500', r.fill]"
                                    :style="{ width: `${(r.score / 10) * 100}%` }"
                                />
                            </div>

                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span :class="['text-xs font-bold', r.scoreClr]">{{ Math.round((r.score / 10) * 100) }}%</span>
                                <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black', r.pillBg, r.pillTxt]">
                                    <Smile v-if="scoreStatus(r.score) === 'Good'" :size="11" />
                                    <Sprout v-else :size="11" />
                                    {{ scoreStatus(r.score) }}
                                </span>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- ──── LEARNING MODULES ──── -->
                <section class="shrink-0 rounded-2xl border border-slate-200 bg-white p-3 sm:p-4">
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-black text-text sm:text-base">
                            <BookOpen :size="18" class="text-primary" />
                            My Learning Modules
                            <Sparkles :size="14" class="text-yellow-400" />
                        </h2>
                        <p class="text-[11px] font-semibold text-slate-500 sm:text-xs">
                            Focus on your current module and have fun learning!
                        </p>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-3 sm:gap-3">

                        <template v-for="m in moduleCards" :key="m.key">

                            <!-- Assigned (clickable) -->
                            <Link
                                v-if="m.assigned"
                                :href="`/learner/modules/${m.key}/start`"
                                class="group relative flex flex-col overflow-hidden rounded-2xl border-2 border-primary bg-linear-to-br from-blue-50 via-white to-white p-3 text-left shadow-lg shadow-primary/10 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl sm:p-3.5"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-white shadow-md">
                                        {{ m.sequence ?? '?' }}
                                    </div>
                                    <span class="rounded-full bg-primary px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white shadow-md shadow-primary/30">
                                        Current Module
                                    </span>
                                </div>

                                <h3 class="mt-2 text-sm font-black leading-tight text-text">{{ m.title }}</h3>
                                <p class="mt-1 line-clamp-2 text-[11px] font-semibold leading-snug text-slate-500">{{ m.blurb }}</p>

                                <div class="flex-1" />

                                <div class="mt-2.5 flex items-center justify-between gap-2 rounded-xl bg-primary px-3 py-2 text-sm font-black text-white shadow-md shadow-primary/30 group-hover:bg-primary-dark sm:px-4">
                                    <span>Continue Learning</span>
                                    <ArrowRight :size="15" class="transition-transform group-hover:translate-x-1" />
                                </div>
                            </Link>

                            <!-- Locked -->
                            <article
                                v-else
                                aria-disabled="true"
                                class="flex cursor-not-allowed flex-col rounded-2xl border-2 border-slate-200 bg-slate-50/60 p-3 opacity-80 sm:p-3.5"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-200 text-sm font-black text-slate-500">
                                        {{ m.sequence ?? '?' }}
                                    </div>
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-200 text-slate-500">
                                        <Lock :size="16" />
                                    </div>
                                </div>

                                <h3 class="mt-2 text-sm font-black leading-tight text-slate-500">{{ m.title }}</h3>
                                <p class="mt-1 line-clamp-2 text-[11px] font-semibold leading-snug text-slate-400">{{ m.blurb }}</p>

                                <div class="flex-1" />

                                <div class="mt-2.5 flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-bold text-slate-500 ring-1 ring-slate-200 sm:px-4">
                                    <Lock :size="13" />
                                    <span v-if="!isDone">Take the diagnostic first</span>
                                    <span v-else>Complete your current module first</span>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>

                <!-- ──── BOTTOM ENCOURAGEMENT BAR ──── -->
                <footer class="flex shrink-0 flex-col items-stretch justify-between gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 sm:flex-row sm:items-center sm:gap-3 sm:px-4">
                    <p class="flex items-center gap-2 text-xs font-semibold text-slate-600 sm:text-sm">
                        <Star :size="16" class="shrink-0 fill-yellow-400 text-yellow-400" />
                        <span class="min-w-0">You're doing amazing! Keep going and enjoy your learning adventure!</span>
                        <Rocket :size="14" class="hidden shrink-0 text-primary sm:block" />
                    </p>
                    <button class="flex shrink-0 items-center justify-center gap-1.5 rounded-full bg-primary px-4 py-2 text-sm font-bold text-white shadow-md shadow-primary/30 transition-colors hover:bg-primary-dark">
                        <HelpCircle :size="14" />
                        Need Help?
                    </button>
                </footer>
            </main>
        </div>
    </div>
</template>
