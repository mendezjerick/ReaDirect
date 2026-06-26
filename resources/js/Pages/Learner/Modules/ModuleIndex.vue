<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import LessonCard from '../../../Components/LessonCard.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import ProgressPath from '../../../Components/ProgressPath.vue';
import { ArrowRight, Play, Pencil, ClipboardCheck, ChevronRight, CheckCircle2 } from 'lucide-vue-next';

defineProps({ module: Object, learnerStage: String, flowState: Object });

const steps = [
    { key: 'start', label: 'Start', description: 'Begin the module', icon: Play, status: 'completed' },
    { key: 'practice', label: 'Practice', description: 'Read and record', icon: Pencil, status: 'current' },
    { key: 'check', label: 'Check', description: 'Review your progress', icon: ClipboardCheck, status: 'locked' },
    { key: 'next', label: 'Next', description: 'Move forward', icon: ChevronRight, status: 'locked' },
];
</script>

<template>
    <LearnerLayout :progress="72" backUrl="/learner/dashboard" backLabel="Back to Learner Dashboard">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="coach_feedback" state="speaking" message="Your practice path is ready. We will work one step at a time." />
        </template>

        <section class="mx-auto grid max-w-3xl gap-5 xl:gap-6">
            <!-- Page Header -->
            <div class="text-center">
                <h1 class="text-3xl font-black text-slate-800 xl:text-4xl">Learning Module</h1>
                <p class="mt-2 text-base font-semibold text-slate-500 xl:text-lg">Let's practice one step at a time.</p>
            </div>

            <!-- Module Card -->
            <LessonCard
                v-if="module"
                :title="module.title"
                :description="module.description"
                active
            />
            <div v-else class="rounded-[32px] border border-slate-200/80 bg-white p-8 text-center shadow-xl shadow-slate-200/30 xl:p-10">
                <p class="text-2xl font-black text-slate-800">No module is needed right now.</p>
                <p class="mt-2 text-lg font-semibold text-slate-500">{{ flowState?.message ?? 'Your diagnostic result shows grade-level readiness.' }}</p>
            </div>

            <!-- Step Progress Path -->
            <ProgressPath v-if="module">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:gap-4">
                    <div
                        v-for="step in steps"
                        :key="step.key"
                        class="group relative flex flex-col items-center gap-3 rounded-[24px] border-2 p-4 text-center transition-all duration-200 xl:rounded-[28px] xl:p-5"
                        :class="{
                            'border-emerald-300 bg-emerald-50/50 shadow-md shadow-emerald-100/50': step.status === 'completed',
                            'border-blue-400 bg-blue-50/50 shadow-lg shadow-blue-200/50 ring-4 ring-blue-100/40': step.status === 'current',
                            'border-slate-200/80 bg-slate-50/30': step.status === 'locked',
                        }"
                    >
                        <!-- Step Icon -->
                        <div
                            class="grid size-12 place-items-center rounded-2xl shadow-sm ring-1 ring-white/30 xl:size-14"
                            :class="{
                                'bg-gradient-to-br from-emerald-400 to-emerald-500 text-white shadow-emerald-500/20': step.status === 'completed',
                                'bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-blue-500/20': step.status === 'current',
                                'bg-slate-100 text-slate-400': step.status === 'locked',
                            }"
                        >
                            <CheckCircle2 v-if="step.status === 'completed'" class="size-6 stroke-[2.5] xl:size-7" />
                            <component :is="step.icon" v-else class="size-5 stroke-[2.5] xl:size-6" />
                        </div>

                        <!-- Step Label -->
                        <div>
                            <p
                                class="text-sm font-black uppercase tracking-widest xl:text-base"
                                :class="{
                                    'text-emerald-600': step.status === 'completed',
                                    'text-blue-600': step.status === 'current',
                                    'text-slate-400': step.status === 'locked',
                                }"
                            >{{ step.label }}</p>
                            <p
                                class="mt-1 text-[13px] font-semibold leading-tight xl:text-[14px]"
                                :class="{
                                    'text-emerald-600/80': step.status === 'completed',
                                    'text-slate-500': step.status === 'current',
                                    'text-slate-400/80': step.status === 'locked',
                                }"
                            >{{ step.description }}</p>
                        </div>

                        <!-- Current indicator dot -->
                        <span
                            v-if="step.status === 'current'"
                            class="absolute -top-1.5 left-1/2 size-3 -translate-x-1/2 rounded-full bg-blue-500 ring-4 ring-blue-100"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </ProgressPath>
        </section>

        <BottomActionBar>
            <div class="flex w-full flex-col-reverse items-center justify-end gap-4 sm:flex-row">
                <Link v-if="module" :href="`/learner/modules/${module.key}/start`" class="w-full sm:w-auto">
                    <PrimaryButton class="group w-full gap-3 rounded-[22px] px-8 py-3.5 text-base shadow-xl shadow-primary/25 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] active:scale-[0.98] sm:w-auto xl:text-lg">
                        Continue
                        <ArrowRight class="size-5 stroke-[3] transition-transform group-hover:translate-x-1 sm:size-6" />
                    </PrimaryButton>
                </Link>
                <Link v-else :href="flowState?.primary_action_route ?? '/learner/dashboard'" class="w-full sm:w-auto">
                    <PrimaryButton class="group w-full gap-3 rounded-[22px] px-8 py-3.5 text-base shadow-xl shadow-primary/25 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] active:scale-[0.98] sm:w-auto xl:text-lg">
                        {{ flowState?.primary_action_label ?? 'Back home' }}
                        <ArrowRight class="size-5 stroke-[3] transition-transform group-hover:translate-x-1 sm:size-6" />
                    </PrimaryButton>
                </Link>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
