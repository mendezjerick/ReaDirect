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
        <div class="anim-fade-down rounded-[36px] border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-200/40 backdrop-blur-md lg:p-8">
            <div class="flex items-center gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-lg shadow-amber-500/30">
                    <Sparkles class="size-8" />
                </span>
                <div>
                    <p class="text-[13px] font-black uppercase tracking-widest text-amber-500">Reward Path</p>
                    <h2 class="text-2xl font-black text-slate-800 lg:text-3xl">Your badges</h2>
                </div>
            </div>

            <div class="anim-stagger mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="reward in rewards"
                    :key="reward.title"
                    class="relative flex flex-col rounded-[32px] p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                    :class="reward.unlocked
                        ? 'bg-gradient-to-br from-amber-50 to-orange-50/50 shadow-lg shadow-amber-500/10 ring-1 ring-amber-400/50'
                        : 'border-2 border-slate-100 bg-white/50 opacity-80 shadow-sm'"
                >
                    <span
                        class="flex size-16 shrink-0 items-center justify-center rounded-[20px] transition-transform duration-300 group-hover:scale-110"
                        :class="reward.unlocked
                            ? 'bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-md shadow-amber-500/40'
                            : 'bg-slate-100 text-slate-300'"
                    >
                        <component :is="reward.icon" class="size-8" :class="reward.unlocked ? 'fill-white/20' : ''" />
                    </span>
                    <h3 class="mt-5 text-lg font-black" :class="reward.unlocked ? 'text-slate-800' : 'text-slate-500'">{{ reward.title }}</h3>
                    <p class="mt-2 flex-1 text-[14px] font-bold leading-relaxed" :class="reward.unlocked ? 'text-slate-600' : 'text-slate-400'">{{ reward.detail }}</p>
                    
                    <div class="mt-6 flex">
                        <span
                            class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-[13px] font-black shadow-sm"
                            :class="reward.unlocked
                                ? 'bg-white text-amber-600 ring-1 ring-amber-200'
                                : 'bg-slate-50 text-slate-400 ring-1 ring-slate-200'"
                        >
                            <CheckCircle2 v-if="reward.unlocked" class="size-4" />
                            {{ reward.unlocked ? 'Unlocked' : 'Locked' }}
                        </span>
                    </div>
                </article>
            </div>
        </div>
    </LearnerSimplePageShell>
</template>

<style scoped>
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-stagger > * {
    opacity: 0;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.anim-stagger > *:nth-child(1) { animation-delay: 100ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 200ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 400ms; }

@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

