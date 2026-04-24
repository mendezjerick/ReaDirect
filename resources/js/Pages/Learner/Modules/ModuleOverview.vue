<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ module: Object, activityTypes: Array, firstActivityType: String });

const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <LearnerLayout :progress="76">
        <template #agent>
            <AgentSpeakerPanel agent-type="coach_feedback" state="speaking" :message="`Welcome to ${module.title}. I will guide your practice one activity at a time.`" />
        </template>

        <section class="mx-auto grid max-w-3xl gap-5">
            <div class="rounded-[32px] border border-border bg-surface p-7 shadow-xl shadow-primary/10">
                <StatusBadge status="Module Overview" variant="primary" />
                <h1 class="mt-4 text-4xl font-black text-text">{{ module.title }}</h1>
                <p class="mt-3 text-xl font-bold leading-relaxed text-muted">{{ module.description }}</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div v-for="activity in activityTypes" :key="activity" class="rounded-3xl border border-primaryLight bg-background px-5 py-4 text-lg font-black text-text">
                        {{ label(activity) }}
                    </div>
                </div>
            </div>
        </section>

        <BottomActionBar>
            <Link v-if="firstActivityType" :href="`/learner/modules/${module.key}/activity/${firstActivityType}`">
                <PrimaryButton>Start practice</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
