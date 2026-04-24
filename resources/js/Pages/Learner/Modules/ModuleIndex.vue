<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import LessonCard from '../../../Components/LessonCard.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import ProgressPath from '../../../Components/ProgressPath.vue';

defineProps({ module: Object, learnerStage: String });
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="coach_feedback" state="speaking" message="Your practice path is ready. We will work one step at a time." />
        </template>

        <section class="mx-auto grid max-w-3xl gap-5 text-center">
            <h1 class="text-4xl font-black text-text">Learning Module</h1>
            <LessonCard
                v-if="module"
                :title="module.title"
                :description="module.description"
                active
            />
            <div v-else class="rounded-[28px] border border-border bg-surface p-8 shadow-lg shadow-primary/10">
                <p class="text-2xl font-black text-text">No module is needed right now.</p>
                <p class="mt-2 text-lg font-bold text-muted">Your diagnostic result shows grade-level readiness.</p>
            </div>
            <ProgressPath v-if="module">
                <div class="grid grid-cols-4 gap-3">
                    <div class="rounded-2xl bg-success/10 px-3 py-4 text-sm font-black text-success">Start</div>
                    <div class="rounded-2xl bg-primaryLight px-3 py-4 text-sm font-black text-primary">Practice</div>
                    <div class="rounded-2xl bg-background px-3 py-4 text-sm font-black text-muted">Check</div>
                    <div class="rounded-2xl bg-background px-3 py-4 text-sm font-black text-muted">Next</div>
                </div>
            </ProgressPath>
        </section>

        <BottomActionBar>
            <Link v-if="module" :href="`/learner/modules/${module.key}/start`">
                <PrimaryButton>Continue</PrimaryButton>
            </Link>
            <Link v-else href="/learner/dashboard">
                <PrimaryButton>Back home</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
