<script setup>
import { Link } from '@inertiajs/vue3';
import { Sparkles, ArrowRight } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
</script>

<template>
    <LearnerLayout :progress="30" diagnostic-step="warm-up">
        <div class="mx-auto grid max-w-2xl gap-6 anim-stagger">
            <PromptCard label="Reading check" prompt="Say each letter clearly." size="sentence" />
            <AgentPanel title="Miss Vivian">I will show one letter at a time.</AgentPanel>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/task-1">
                <PrimaryButton class="gap-2">
                    <Sparkles class="size-5" />
                    Start
                    <ArrowRight class="size-5 transition-transform duration-200 group-hover:translate-x-1" />
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
