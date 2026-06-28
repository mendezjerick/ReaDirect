<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { BookOpen, ArrowRight } from 'lucide-vue-next';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';

const props = defineProps({
    stories: Array,
    assessmentAttemptId: Number,
});

const form = useForm({
    assessment_attempt_id: props.assessmentAttemptId,
    passage_id: '',
});

const selectedStory = computed(() => (props.stories ?? []).find((story) => story.id === form.passage_id));

const chooseStory = (story) => {
    form.passage_id = story.id;
};

const submit = () => {
    if (!form.passage_id) return;
    form.post('/final-assessment/story-selection/submit');
};
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                state="speaking"
                presentation="reading-intro"
                message="Choose one story for your final reading passage."
                line-key="vivian.assessment.story_choice"
            />
        </template>

        <section class="mx-auto grid w-full max-w-[880px] gap-5">
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="story in stories"
                    :key="story.id"
                    type="button"
                    class="grid min-h-56 content-center gap-4 rounded-[28px] border-2 bg-white p-6 text-left shadow-xl shadow-slate-200/30 transition"
                    :class="form.passage_id === story.id ? 'border-primary text-primary ring-4 ring-primary/10' : 'border-slate-200 text-slate-800 hover:border-primary/40'"
                    @click="chooseStory(story)"
                >
                    <span class="inline-flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <BookOpen class="size-8 stroke-[2.5]" />
                    </span>
                    <span class="grid gap-2">
                        <span class="text-3xl font-black">Story {{ story.story_number }}</span>
                        <span class="text-xl font-black text-slate-700">{{ story.title }}</span>
                    </span>
                </button>
            </div>

            <p v-if="form.errors.passage_id" class="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">
                {{ form.errors.passage_id }}
            </p>
        </section>

        <BottomActionBar>
            <PrimaryButton :disabled="form.processing || !selectedStory" class="ml-auto gap-3" @click="submit">
                Start Story {{ selectedStory?.story_number ?? '' }}
                <ArrowRight class="size-5 stroke-[3]" />
            </PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
