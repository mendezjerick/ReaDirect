<script setup>
import { computed } from 'vue';
import { Award, BookOpen, CheckCircle2, Sparkles, Star, Trophy } from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    latestAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
});

const diagnosticDone = computed(() => props.flowState?.diagnostic?.is_completed === true);
const moduleStarted = computed(() => Boolean(props.flowState?.module?.current_module_key ?? props.flowState?.current_module_key));
const readingDone = computed(() => Number(props.latestAttempt?.reading_accuracy ?? 0) > 0);

const rewards = computed(() => [
    { title: 'Diagnostic Explorer', detail: 'Complete the first reading check.', unlocked: diagnosticDone.value, icon: Trophy },
    { title: 'Module Starter', detail: 'Open your assigned learning module.', unlocked: moduleStarted.value, icon: BookOpen },
    { title: 'Passage Reader', detail: 'Finish a passage reading check.', unlocked: readingDone.value, icon: Star },
    { title: 'Path Builder', detail: 'Keep practicing until the next checkpoint.', unlocked: false, icon: Award },
]);
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Rewards"
        subtitle="Milestones from your reading journey"
        active="rewards"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-xl bg-accent/20 text-yellow-600">
                    <Sparkles class="size-7" />
                </span>
                <div>
                    <p class="text-sm font-black uppercase text-primary">Reward Path</p>
                    <h2 class="text-2xl font-black text-text">Your badges</h2>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="reward in rewards"
                    :key="reward.title"
                    class="rounded-2xl border p-4"
                    :class="reward.unlocked ? 'border-yellow-200 bg-yellow-50/70' : 'border-slate-200 bg-slate-50'"
                >
                    <span
                        class="grid size-14 place-items-center rounded-2xl"
                        :class="reward.unlocked ? 'bg-yellow-100 text-yellow-600' : 'bg-white text-slate-400'"
                    >
                        <component :is="reward.icon" class="size-8" />
                    </span>
                    <h3 class="mt-4 text-lg font-black text-text">{{ reward.title }}</h3>
                    <p class="mt-2 text-sm font-bold leading-relaxed text-slate-600">{{ reward.detail }}</p>
                    <p
                        class="mt-4 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-black"
                        :class="reward.unlocked ? 'bg-success/10 text-success' : 'bg-white text-slate-500'"
                    >
                        <CheckCircle2 v-if="reward.unlocked" class="size-4" />
                        {{ reward.unlocked ? 'Unlocked' : 'Locked' }}
                    </p>
                </article>
            </div>
        </section>
    </LearnerSimplePageShell>
</template>
