<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({
    attempt: Object,
});
</script>

<template>
    <LearnerLayout :progress="48" diagnostic-step="task-2a">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="encouraging"
                message="Task 2A is now saved. Based on this path, the next reading parts will not be given for now."
                line-key="estelle.result.task2a.saved"
            />
        </template>

        <section class="anim-stagger relative mx-auto grid max-w-3xl gap-6 text-center">
            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
            <div class="pointer-events-none absolute -right-16 bottom-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />

            <!-- Badge -->
            <div class="flex justify-center">
                <RewardBadge title="Task 2A Complete" />
            </div>

            <!-- Heading -->
            <div>
                <h1 class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-4xl font-black text-transparent">
                    Rhyming word score
                </h1>
                <p class="mx-auto mt-3 max-w-2xl text-[15px] font-semibold leading-relaxed text-slate-400">
                    This score is saved on your diagnostic record. Your CRLA summary will show the completed Task 1 and Task 2A results.
                </p>
            </div>

            <!-- Score cards -->
            <div class="grid gap-4 lg:grid-cols-2">
                <ScoreCard label="Task 1 letters" :value="attempt.task_1_score" />
                <ScoreCard label="Task 2A rhymes" :value="attempt.task_2a_score" />
            </div>

            <!-- What happens next -->
            <div class="anim-card rounded-[32px] border border-slate-200/80 bg-white p-6 text-left shadow-xl shadow-slate-200/30">
                <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">What happens next</p>
                <p class="mt-3 text-[15px] font-semibold leading-relaxed text-slate-500">
                    Task 2B and passage reading are not administered when Task 1A is 0-6. The CRLA summary records Task 2B and passage score as 0.
                </p>
            </div>

            <!-- Sparkle decoration -->
            <span class="pointer-events-none absolute -right-6 top-4 text-4xl font-black text-primary/5">✦</span>
        </section>

        <BottomActionBar>
            <Link href="/learner/diagnostic/crla-summary">
                <PrimaryButton>Continue to CRLA Summary</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}

.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
.anim-stagger > *:nth-child(5) { animation-delay: 600ms; }
.anim-stagger > *:nth-child(6) { animation-delay: 750ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
