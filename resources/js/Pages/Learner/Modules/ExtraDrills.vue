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
            <AgentSpeakerPanel agent-type="coach_feedback" state="encouraging" message="Extra sound practice will be added here. For now, return to the module and practice again." />
        </template>

        <section class="mx-auto max-w-2xl rounded-[32px] border border-border bg-surface p-8 text-center shadow-xl shadow-primary/10">
            <h1 class="text-4xl font-black text-text">Extra Phoneme Drills</h1>
            <p class="mt-4 text-xl font-bold text-muted">This placeholder keeps the Phase 3 path ready for focused sound drills.</p>
        </section>

        <BottomActionBar>
            <Link :href="`/learner/modules/${module.key}/start`">
                <PrimaryButton>Practice again</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
