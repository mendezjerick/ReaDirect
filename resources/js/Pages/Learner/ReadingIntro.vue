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
                message="Read the passage aloud first. Then answer five questions."
            />
        </template>

        <div class="relative mx-auto grid w-full max-w-[980px] gap-6 pb-2">
            <span class="pointer-events-none absolute -left-4 top-12 hidden text-2xl font-black text-blue-200 xl:block" aria-hidden="true">*</span>
            <span class="pointer-events-none absolute right-12 top-5 text-4xl font-black text-accent" aria-hidden="true">*</span>
            <span class="pointer-events-none absolute right-0 top-28 hidden text-2xl font-black text-blue-200 sm:block" aria-hidden="true">*</span>
            <span class="pointer-events-none absolute -right-8 bottom-8 hidden text-3xl font-black text-blue-200 xl:block" aria-hidden="true">*</span>

            <section class="relative overflow-hidden rounded-[28px] border border-blue-100 bg-surface px-6 py-10 text-center shadow-lg shadow-primary/10 sm:px-10 sm:py-12">
                <span class="absolute bottom-0 left-0 h-16 w-28 rounded-tr-full bg-blue-100" aria-hidden="true" />
                <span class="absolute bottom-8 right-20 text-xl font-black text-blue-100" aria-hidden="true">*</span>
                <span class="absolute bottom-6 right-10 text-2xl font-black text-blue-200" aria-hidden="true">*</span>
                <div class="relative z-10 grid justify-items-center gap-5">
                    <p class="inline-flex items-center gap-3 text-xl font-black text-primary">
                        <Sparkles class="size-5 fill-primary/40 text-primary/70" />
                        Reading passage
                        <Sparkles class="size-5 fill-primary/40 text-primary/70" />
                    </p>
                    <h1 class="text-5xl font-black leading-tight text-text sm:text-6xl">Read aloud.</h1>
                </div>
            </section>

            <section class="relative overflow-hidden rounded-[22px] border border-blue-100 bg-surface px-7 py-6 shadow-lg shadow-primary/10">
                <span class="absolute right-9 top-6 text-4xl font-black text-blue-100" aria-hidden="true">*</span>
                <div class="relative z-10">
                    <p class="text-base font-black uppercase text-primary">Miss Vivian</p>
                    <p class="mt-4 text-xl font-black leading-relaxed text-text">
                        Read the passage clearly. Then answer five short questions.
                    </p>
                </div>
            </section>
        </div>

        <BottomActionBar>
            <Link href="/learner/diagnostic/passage">
                <PrimaryButton class="gap-3 rounded-[18px] px-8 shadow-xl shadow-primary/25 sm:min-w-[230px]">
                    Start passage
                    <ArrowRight class="size-5 stroke-[3]" />
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
