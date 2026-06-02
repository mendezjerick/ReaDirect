<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { Play, Pause } from 'lucide-vue-next';

const props = defineProps({
    src: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['play', 'pause', 'ended']);

const audioEl = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);

// Format time in M:SS
const formatTime = (time) => {
    if (isNaN(time) || !isFinite(time)) return '0:00';
    const m = Math.floor(time / 60);
    const s = Math.floor(time % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
};

const togglePlay = () => {
    if (props.disabled || !audioEl.value) return;
    if (isPlaying.value) {
        audioEl.value.pause();
    } else {
        audioEl.value.play();
    }
};

const onTimeUpdate = () => {
    if (!audioEl.value) return;
    currentTime.value = audioEl.value.currentTime;
};

const onLoadedMetadata = () => {
    if (!audioEl.value) return;
    duration.value = audioEl.value.duration;
};

const onPlay = () => {
    isPlaying.value = true;
    emit('play');
};

const onPause = () => {
    isPlaying.value = false;
    emit('pause');
};

const onEnded = () => {
    isPlaying.value = false;
    currentTime.value = 0;
    emit('ended');
};

watch(() => props.src, () => {
    isPlaying.value = false;
    currentTime.value = 0;
});

onBeforeUnmount(() => {
    if (audioEl.value) {
        audioEl.value.pause();
    }
});
</script>

<template>
    <div class="relative flex items-center gap-4 rounded-[32px] border border-blue-100 bg-white p-2.5 pr-6 shadow-md shadow-blue-500/10 transition-all duration-200 xl:p-3 xl:pr-7">
        <!-- Hidden Audio Element -->
        <audio
            ref="audioEl"
            class="hidden"
            :src="src"
            @timeupdate="onTimeUpdate"
            @loadedmetadata="onLoadedMetadata"
            @play="onPlay"
            @pause="onPause"
            @ended="onEnded"
        />

        <!-- Play/Pause Button -->
        <button
            type="button"
            class="group grid size-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-sm ring-1 ring-blue-500/20 transition-all duration-200 hover:scale-[1.05] hover:shadow-md active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 xl:size-14"
            :disabled="disabled"
            @click="togglePlay"
            :aria-label="isPlaying ? 'Pause recorded audio' : 'Play recorded audio'"
        >
            <Pause v-if="isPlaying" class="size-5 fill-white xl:size-6" />
            <Play v-else class="ml-1 size-5 fill-white xl:size-6" />
        </button>

        <!-- Simulated Waveform Visualization -->
        <div class="flex h-8 flex-1 items-center gap-1 xl:h-10">
            <span
                v-for="bar in 24"
                :key="bar"
                class="w-full rounded-full transition-all duration-150"
                :class="[
                    isPlaying ? 'bg-blue-500' : 'bg-blue-200',
                    (currentTime / Math.max(duration, 0.1)) > (bar / 24) ? 'opacity-100' : 'opacity-40',
                    isPlaying ? 'playing-wave' : ''
                ]"
                :style="{
                    height: `${30 + (bar * 7 % 70)}%`,
                    animationDelay: `${bar * 0.05}s`,
                    animationDuration: `${0.8 + (bar % 3) * 0.2}s`
                }"
            />
        </div>

        <!-- Time Display -->
        <div class="shrink-0 text-right min-w-[3rem]">
            <p class="text-[14px] font-black tracking-tight text-blue-600 xl:text-[15px]">{{ formatTime(currentTime) }}</p>
            <p class="text-[11px] font-bold text-slate-400 xl:text-[12px]">{{ formatTime(duration) }}</p>
        </div>
    </div>
</template>

<style scoped>
.playing-wave {
    animation-name: wave-bounce;
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
    animation-direction: alternate;
}

@keyframes wave-bounce {
    0% { transform: scaleY(0.7); }
    100% { transform: scaleY(1.3); }
}
</style>
