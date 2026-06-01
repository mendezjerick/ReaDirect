<script setup>
import { useForm } from '@inertiajs/vue3';
import { BookOpenCheck, ArrowRight, Sparkles, Star, Loader2 } from 'lucide-vue-next';
import SyncStatusBadge from '../../Components/SyncStatusBadge.vue';

const form = useForm({ learner_code: 'RD-1001' });
const submit = () => form.post('/learner/access');
</script>

<template>
    <div class="access-page relative flex min-h-screen flex-col overflow-hidden bg-gradient-to-b from-blue-50 via-white to-blue-50/50 font-sans text-text">

        <!-- ═══ Decorative background elements ═══ -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <!-- Large soft gradient circles -->
            <div class="absolute -left-32 -top-32 h-[400px] w-[400px] rounded-full bg-primary/5 blur-3xl" />
            <div class="absolute -bottom-40 -right-40 h-[500px] w-[500px] rounded-full bg-blue-400/5 blur-3xl" />
            <div class="absolute left-1/2 top-1/3 h-[300px] w-[300px] -translate-x-1/2 rounded-full bg-violet-400/3 blur-3xl" />

            <!-- Floating stars -->
            <Star class="absolute left-[12%] top-[22%] size-5 fill-yellow-300/40 text-yellow-300/40 floating-star" />
            <Star class="absolute right-[15%] top-[18%] size-4 fill-yellow-300/30 text-yellow-300/30 floating-star-delayed" />
            <Star class="absolute left-[8%] bottom-[30%] size-3 fill-primary/20 text-primary/20 floating-star" />
            <Star class="absolute right-[10%] bottom-[25%] size-4 fill-blue-400/25 text-blue-400/25 floating-star-delayed" />
            <Star class="absolute left-[45%] top-[12%] size-3 fill-yellow-400/30 text-yellow-400/30 floating-star" />

            <!-- Soft cloud shapes -->
            <div class="absolute left-[5%] top-[35%] h-12 w-28 rounded-full bg-white/60 blur-md sm:h-16 sm:w-36" />
            <div class="absolute right-[8%] top-[45%] h-10 w-24 rounded-full bg-white/50 blur-md sm:h-14 sm:w-32" />
            <div class="absolute left-[20%] bottom-[15%] h-10 w-20 rounded-full bg-white/40 blur-md" />
        </div>

        <!-- ═══ Top bar ═══ -->
        <header class="anim-fade-down sticky top-0 z-20 border-b border-blue-100/60 bg-white/80 backdrop-blur-lg">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
                <!-- Logo -->
                <a href="/" class="group inline-flex items-center gap-2.5 text-xl font-black text-primary transition-all hover:scale-[1.02] sm:text-2xl">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-md shadow-primary/20 transition-shadow group-hover:shadow-lg group-hover:shadow-primary/30">
                        <BookOpenCheck class="size-6" />
                    </span>
                    ReaDirect
                </a>

                <!-- Progress bar (thin, minimal) -->
                <div class="mx-6 hidden h-2 flex-1 overflow-hidden rounded-full bg-blue-100/60 shadow-inner sm:block">
                    <div class="h-full w-[10%] rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500" />
                </div>

                <!-- Sync badge -->
                <SyncStatusBadge />
            </div>
        </header>

        <!-- ═══ Main content ═══ -->
        <main class="relative z-10 flex flex-1 flex-col items-center justify-center px-4 py-8 sm:py-12">
            <div class="w-full max-w-lg">

                <!-- Welcome card -->
                <div class="welcome-card relative overflow-hidden rounded-[40px] border-[3px] border-primary/10 bg-white p-8 text-center shadow-2xl shadow-primary/10 sm:p-10">
                    <!-- Decorative blobs inside the card -->
                    <div class="pointer-events-none absolute -left-12 -top-12 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-12 -right-12 h-40 w-40 rounded-full bg-violet-400/5 blur-3xl" />

                    <!-- Sparkle icon -->
                    <div class="welcome-icon relative mx-auto flex h-16 w-16 items-center justify-center rounded-[22px] bg-gradient-to-br from-primary to-blue-600 text-white shadow-xl shadow-primary/25">
                        <BookOpenCheck class="size-8" />
                        <Sparkles class="absolute -right-2 -top-2 size-5 fill-yellow-400 text-yellow-400 animate-pulse" />
                    </div>

                    <!-- Welcome text -->
                    <p class="welcome-label relative mt-5 text-[14px] font-black uppercase tracking-[0.2em] text-primary/60">
                        Welcome reader
                    </p>
                    <h1 class="welcome-title relative mt-2 bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-[clamp(2.5rem,6vw,3.5rem)] font-black leading-tight text-transparent">
                        Ready to read?
                    </h1>
                    <p class="welcome-subtitle relative mt-3 text-[15px] font-semibold leading-relaxed text-slate-400">
                        Enter your learner code to begin your reading journey.
                    </p>
                </div>

                <!-- Form card -->
                <form
                    class="form-card relative mt-5 overflow-hidden rounded-[32px] border-[3px] bg-white p-6 shadow-xl sm:p-8"
                    :class="form.errors.learner_code ? 'border-rose-200/60' : 'border-slate-200/60'"
                    @submit.prevent="submit"
                >
                    <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-primary/3 blur-2xl" />

                    <!-- Label -->
                    <label for="learner_code" class="relative flex items-center gap-2 text-[14px] font-black uppercase tracking-widest text-slate-400">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-blue-600 text-[10px] font-black text-white shadow-sm">
                            ID
                        </span>
                        Learner code
                    </label>

                    <!-- Input -->
                    <input
                        id="learner_code"
                        v-model="form.learner_code"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        :class="[
                            'relative mt-3 w-full rounded-[20px] border-[3px] bg-slate-50/50 px-6 py-4 text-2xl font-black uppercase tracking-widest transition-all duration-200 placeholder:text-slate-300 focus:bg-white focus:outline-none focus:ring-4 sm:text-3xl',
                            form.errors.learner_code
                                ? 'border-rose-300 text-rose-700 focus:border-rose-400 focus:ring-rose-100'
                                : 'border-slate-200/80 text-slate-800 focus:border-primary focus:ring-primary/10'
                        ]"
                        placeholder="RD-0000"
                    >

                    <!-- Error message -->
                    <Transition name="error-slide">
                        <div v-if="form.errors.learner_code" class="mt-3 flex items-start gap-2 rounded-2xl bg-rose-50 p-3.5 ring-1 ring-rose-200/60">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-rose-100 text-[10px] font-black text-rose-500">!</span>
                            <p class="text-[14px] font-bold leading-snug text-rose-600">{{ form.errors.learner_code }}</p>
                        </div>
                    </Transition>

                    <!-- Helper text -->
                    <p v-if="!form.errors.learner_code" class="mt-3 text-center text-[12px] font-semibold text-slate-300">
                        Ask your teacher if you need your learner code.
                    </p>

                    <!-- Submit button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="submit-btn group relative mt-5 flex w-full items-center justify-center gap-3 overflow-hidden rounded-[20px] bg-gradient-to-r from-primary to-blue-600 px-8 py-5 text-[18px] font-black text-white shadow-xl shadow-primary/20 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-2xl hover:shadow-primary/30 focus:outline-none focus:ring-4 focus:ring-primary/20 active:scale-[0.98] active:shadow-lg disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:scale-100 disabled:hover:shadow-xl sm:text-[20px]"
                    >
                        <!-- Shimmer overlay -->
                        <span class="pointer-events-none absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent opacity-0 transition-opacity group-hover:opacity-100" />

                        <template v-if="form.processing">
                            <Loader2 class="size-6 animate-spin" />
                            <span>Checking...</span>
                        </template>
                        <template v-else>
                            <span>Continue</span>
                            <ArrowRight class="size-5 transition-transform duration-200 group-hover:translate-x-1" />
                        </template>
                    </button>
                </form>

                <!-- Bottom stars -->
                <div class="bottom-stars mt-6 flex items-center justify-center gap-1.5">
                    <Star v-for="i in 5" :key="i" class="size-4 fill-yellow-400/50 text-yellow-400/50" />
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
/* ── Welcome card entrance ── */
.welcome-card {
    animation: cardSpring 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(30px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ── Welcome icon bounce ── */
.welcome-icon {
    animation: iconBounce 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.2s;
    opacity: 0;
}
@keyframes iconBounce {
    from { opacity: 0; transform: scale(0.5) translateY(10px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ── Welcome text stagger ── */
.welcome-label {
    animation: textFade 0.5s ease-out forwards;
    animation-delay: 0.3s;
    opacity: 0;
}
.welcome-title {
    animation: textFade 0.5s ease-out forwards;
    animation-delay: 0.4s;
    opacity: 0;
}
.welcome-subtitle {
    animation: textFade 0.5s ease-out forwards;
    animation-delay: 0.5s;
    opacity: 0;
}
@keyframes textFade {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Form card entrance ── */
.form-card {
    animation: formSlide 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.3s;
    opacity: 0;
}
@keyframes formSlide {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Header entrance ── */
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Bottom stars entrance ── */
.bottom-stars {
    animation: starsIn 0.6s ease-out forwards;
    animation-delay: 0.6s;
    opacity: 0;
}
@keyframes starsIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

/* ── Floating star animations ── */
.floating-star {
    animation: floatStar 6s ease-in-out infinite;
}
.floating-star-delayed {
    animation: floatStar 7s ease-in-out infinite;
    animation-delay: 2s;
}
@keyframes floatStar {
    0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.4; }
    50% { transform: translateY(-12px) rotate(15deg); opacity: 0.7; }
}

/* ── Error slide transition ── */
.error-slide-enter-active {
    animation: errorIn 0.3s ease-out forwards;
}
.error-slide-leave-active {
    animation: errorIn 0.2s ease-in reverse forwards;
}
@keyframes errorIn {
    from { opacity: 0; transform: translateY(-8px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
</style>
