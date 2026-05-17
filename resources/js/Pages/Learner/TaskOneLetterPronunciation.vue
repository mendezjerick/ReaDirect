<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import RecordingButton from '../../Components/RecordingButton.vue';
import AudioLevelMeter from '../../Components/AudioLevelMeter.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({ letters: Array });
const index = ref(0);
const score = ref(0);
const state = ref('ready');
const responses = ref([]);
const current = computed(() => props.letters[index.value] ?? { prompt: 'A' });
const progress = computed(() => 35 + Math.round(((index.value + 1) / Math.max(props.letters.length, 1)) * 35));

const record = async () => {
    state.value = 'recording';
    try {
        const stream = await navigator.mediaDevices?.getUserMedia({ audio: true });
        const recorder = new MediaRecorder(stream);
        recorder.start();
        setTimeout(() => {
            recorder.stop();
            stream.getTracks().forEach((track) => track.stop());
            state.value = 'done';
        }, 900);
    } catch {
        state.value = 'retry';
    }
};

const mark = (isCorrect) => {
    responses.value.push({ prompt: current.value.prompt, is_correct: isCorrect });
    score.value += isCorrect ? 1 : 0;
    if (index.value < props.letters.length - 1) {
        index.value += 1;
        state.value = 'ready';
        return;
    }

    router.post('/learner/diagnostic/task-1', { score: score.value, responses: responses.value });
};
</script>

<template>
    <LearnerLayout :progress="progress" diagnostic-step="task-1">
        <div class="mx-auto grid max-w-2xl gap-6">
            <div class="flex items-center justify-between">
                <StatusBadge :status="`Letter ${index + 1} of ${letters.length}`" />
                <StatusBadge :status="state" :variant="state === 'retry' ? 'warning' : 'primary'" />
            </div>
            <PromptCard label="Say this letter" :prompt="current.prompt" size="letter" />
            <RecordingButton :state="state" @click="record" />
            <AudioLevelMeter :level="state === 'recording' ? 72 : 30" />
            <div class="grid grid-cols-2 gap-3">
                <SecondaryButton @click="mark(false)">Try again later</SecondaryButton>
                <PrimaryButton @click="mark(true)">Sounds good</PrimaryButton>
            </div>
        </div>
        <BottomActionBar>
            <p class="text-lg font-black text-muted">Score: {{ score }} / 10</p>
        </BottomActionBar>
    </LearnerLayout>
</template>
