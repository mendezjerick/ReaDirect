<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';

defineProps({ module: Object });
</script>

<template>
    <LearnerLayout :progress="68">
        <template #agent>
            <AgentSpeakerPanel agent-type="coach_feedback" state="encouraging" message="Let us practice some sounds together. These drills will help before trying Module 1 again." />
        </template>

        <section class="mx-auto grid max-w-2xl gap-4 rounded-[32px] border border-border bg-surface p-8 text-center shadow-xl shadow-primary/10">
            <h1 class="text-4xl font-black text-text">Extra Sound Practice</h1>
            <p class="text-xl font-bold text-muted">Let us practice some sounds together.</p>
            <p class="rounded-2xl bg-primaryLight px-4 py-3 text-base font-black text-primaryDark">
                When you are ready, go back to Module 1 and try the practice again.
            </p>
        </section>

        <BottomActionBar>
            <Link :href="`/learner/modules/${module.key}/start`">
                <PrimaryButton>Practice Module 1 again</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
