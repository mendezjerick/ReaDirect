<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
</script>

<template>
    <LearnerLayout :progress="70" diagnostic-step="sentence-reading">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                state="speaking"
                presentation="reading-intro"
                message="Choose a story, read it aloud, then answer four questions."
            />
        </template>

        <div class="anim-stagger relative mx-auto grid w-full max-w-[980px] gap-6 pb-2">
            <!-- Sparkle decorations -->
            <span class="pointer-events-none absolute -left-4 top-12 hidden text-2xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute right-12 top-5 text-4xl font-black text-yellow-500" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute right-0 top-28 hidden text-2xl font-black text-primary/5 sm:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute -right-8 bottom-8 hidden text-3xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>

            <!-- Hero card -->
            <section class="anim-hero relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white px-6 py-6 text-center shadow-2xl shadow-primary/10 sm:px-10 sm:py-12">
                <!-- Decorative blur blobs -->
                <span class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
                <span class="pointer-events-none absolute -bottom-10 -right-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" aria-hidden="true" />

                <!-- Bottom-left decorative shape -->
                <span class="absolute bottom-0 left-0 h-16 w-28 rounded-tr-full bg-primary/5" aria-hidden="true" />
                <!-- Inner sparkles -->
                <span class="pointer-events-none absolute bottom-8 right-20 text-xl font-black text-yellow-500" aria-hidden="true">✦</span>
                <span class="pointer-events-none absolute bottom-6 right-10 text-2xl font-black text-primary/5" aria-hidden="true">✦</span>

                <div class="relative z-10 grid justify-items-center gap-5">
                    <p class="inline-flex items-center gap-3 text-xl font-black text-primary">
                        <Sparkles class="size-5 fill-primary/40 text-primary/70" />
                        Reading passage
                        <Sparkles class="size-5 fill-primary/40 text-primary/70" />
                    </p>
                    <h1 class="anim-text-pop text-5xl font-black leading-tight sm:text-6xl">
                        <span class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-transparent">Read aloud.</span>
                    </h1>
                </div>
            </section>

            <!-- Agent message card -->
            <section class="relative overflow-hidden rounded-[28px] border border-slate-200/80 bg-white px-7 py-6 shadow-xl shadow-slate-200/30">
                <span class="pointer-events-none absolute right-9 top-6 text-4xl font-black text-primary/5" aria-hidden="true">✦</span>
                <div class="relative z-10">
                    <p class="text-[14px] font-black uppercase tracking-widest text-primary">Miss Vivian</p>
                    <p class="mt-4 text-xl font-black leading-relaxed text-slate-800">
                        Choose a story. Read the passage clearly, then answer four short questions.
                    </p>
                </div>
            </section>
        </div>

        <BottomActionBar>
            <Link href="/learner/diagnostic/story-selection" class="w-full sm:w-auto">
                <PrimaryButton class="w-full gap-3 rounded-[22px] px-5 text-base shadow-xl shadow-primary/25 sm:w-auto sm:min-w-[320px] sm:gap-4 sm:px-9 sm:text-lg">
                    Choose story
                    <ArrowRight class="size-5 stroke-[3] sm:size-6" />
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* Staggered children */
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Hero card spring */
.anim-hero {
    animation: heroSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes heroSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Title text pop */
.anim-text-pop {
    animation: textPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.2s;
    opacity: 0;
}
@keyframes textPop {
    from { opacity: 0; transform: scale(0.7); }
    to   { opacity: 1; transform: scale(1); }
}
</style>
