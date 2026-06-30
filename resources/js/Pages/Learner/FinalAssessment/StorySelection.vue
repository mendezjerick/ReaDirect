<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Check } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';

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
    <GuideLayout
        :progress="72"
        eyebrow="Story Selection"
        divider-label="Choose one"
        agent-message="Choose one story for your final reading passage."
        agent-line-key="vivian.assessment.story_choice"
        :primary-label="`Start story ${selectedStory?.story_number ?? ''}`"
        :primary-disabled="form.processing || !selectedStory"
        @primary="submit"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Choose your <span class="guide-title-accent">story.</span>
        </template>

        <div class="guide-story-grid">
            <button
                v-for="(story, index) in stories"
                :key="story.id"
                type="button"
                class="guide-story-option guide-anim"
                :class="{ 'guide-story-option--selected': form.passage_id === story.id }"
                :style="`--guide-delay: ${200 + index * 85}ms`"
                @click="chooseStory(story)"
            >
                <div class="rd-card">
                    <div class="rd-card__face guide-story-card">
                        <span
                            class="guide-story-check"
                            :class="{ 'guide-story-check--on': form.passage_id === story.id }"
                            aria-hidden="true"
                        >
                            <Check class="size-3.5 stroke-[3.5]" />
                        </span>
                        <span class="guide-story-badge">{{ story.story_number }}</span>
                        <span class="guide-story-body">
                            <span class="guide-story-label">Story {{ story.story_number }}</span>
                            <span class="guide-story-title">{{ story.title }}</span>
                        </span>
                        <span
                            class="guide-story-cue"
                            :class="{ 'guide-story-cue--active': form.passage_id === story.id }"
                        >
                            {{ form.passage_id === story.id ? 'Selected' : 'Tap to select' }}
                        </span>
                    </div>
                </div>
            </button>
        </div>

        <p v-if="form.errors.passage_id" class="guide-status guide-status--error">
            {{ form.errors.passage_id }}
        </p>
    </GuideLayout>
</template>
