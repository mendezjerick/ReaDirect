<script setup>
import { computed } from 'vue';
import { Bot, CheckCircle2, AlertTriangle, ExternalLink } from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: Object,
        default: () => ({}),
    },
    troubleshootingHref: {
        type: String,
        default: null,
    },
    guideHref: {
        type: String,
        default: null,
    },
});

const isConnected = computed(() => props.status?.connected === true);
const isDisabled = computed(() => props.status?.status === 'disabled');
const tone = computed(() => {
    if (isConnected.value) return 'connected';
    if (isDisabled.value) return 'disabled';
    return 'unavailable';
});

const styles = computed(() => ({
    connected: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    disabled: 'border-amber-200 bg-amber-50 text-amber-800',
    unavailable: 'border-red-200 bg-red-50 text-red-800',
}[tone.value]));

const iconStyles = computed(() => ({
    connected: 'bg-emerald-100 text-emerald-600',
    disabled: 'bg-amber-100 text-amber-600',
    unavailable: 'bg-red-100 text-red-600',
}[tone.value]));

const reported = (value) => {
    if (value === true) return 'Yes';
    if (value === false) return 'No';
    if (Array.isArray(value)) return value.length ? value.join(', ') : 'Not reported';
    if (value && typeof value === 'object') return Object.keys(value).length ? JSON.stringify(value) : 'Not reported';
    const text = String(value ?? '').trim();
    return text || 'Not reported';
};

const details = computed(() => [
    props.status?.base_url ? `URL: ${props.status.base_url}` : null,
    `Architecture: ${reported(props.status?.asr_architecture ?? 'wav2vec2_only')}`,
    `ASR Model: ${reported(props.status?.active_asr_model ?? props.status?.wav2vec2_asr_model_name ?? props.status?.model_used ?? props.status?.model_size)}`,
    `Whisper removed: ${reported(props.status?.whisper_removed ?? true)}`,
].filter(Boolean));

const modelRows = computed(() => [
    ['Active ASR Architecture', reported(props.status?.asr_architecture ?? 'wav2vec2_only')],
    ['ASR Model', reported(props.status?.active_asr_model ?? props.status?.wav2vec2_asr_model_name ?? props.status?.model_used ?? props.status?.model_size ?? 'Fine-tuned Wav2Vec2 mixed model')],
    ['Model Path', reported(props.status?.model_used ?? props.status?.wav2vec2_asr_model_name)],
    ['Phoneme Support', reported(props.status?.wav2vec2_phoneme_available)],
    ['Phoneme Model Path', reported(props.status?.wav2vec2_phoneme_model_name)],
    ['Supported Prompt Types', reported(props.status?.supported_prompt_types)],
]);

const correctionRows = computed(() => [
    ['Correction Layer', reported(props.status?.correction_layer_enabled)],
    ['Expected-centric Scoring', reported(props.status?.expected_centric_scoring_enabled)],
    ['Phoneme Evidence', reported(props.status?.phoneme_evidence_enabled)],
    ['Thresholds', reported(props.status?.thresholds)],
    ['Loaded Model Paths', reported(props.status?.local_model_paths_loaded)],
    ['Whisper Runtime', props.status?.whisper_removed === false ? 'Reported available' : 'Removed from runtime'],
]);
</script>

<template>
    <section class="mb-5 rounded-lg border px-4 py-3 shadow-sm" :class="styles">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="flex gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="iconStyles">
                    <CheckCircle2 v-if="isConnected" :size="18" />
                    <AlertTriangle v-else-if="!isDisabled" :size="18" />
                    <Bot v-else :size="18" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-extrabold">{{ status?.label ?? 'AI service status unavailable' }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed opacity-90">{{ status?.message }}</p>
                    <p v-if="isConnected" class="mt-2 text-xs font-extrabold">
                        Active ASR Architecture: Wav2Vec2-only ASR runtime
                    </p>
                    <a
                        v-if="guideHref"
                        :href="guideHref"
                        class="mt-1 inline-flex items-center gap-1 text-xs font-extrabold underline decoration-current/40 underline-offset-2 hover:decoration-current"
                    >
                        Which environment values should I use?
                        <ExternalLink :size="12" />
                    </a>
                    <div v-if="details.length" class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="detail in details"
                            :key="detail"
                            class="rounded-md bg-white/60 px-2 py-1 text-[11px] font-bold"
                        >
                            {{ detail }}
                        </span>
                    </div>
                </div>
            </div>

            <a
                v-if="troubleshootingHref && !isConnected"
                :href="troubleshootingHref"
                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-white/75 px-3 py-2 text-xs font-extrabold shadow-sm transition hover:bg-white"
            >
                Troubleshoot
                <ExternalLink :size="13" />
            </a>
        </div>

        <ol v-if="!isConnected && status?.troubleshooting_steps?.length" class="mt-3 list-decimal space-y-1 pl-12 text-xs font-semibold leading-relaxed">
            <li v-for="step in status.troubleshooting_steps" :key="step">{{ step }}</li>
        </ol>

        <details v-if="isConnected" class="mt-3 rounded-lg bg-white/55 px-3 py-2 text-xs">
            <summary class="cursor-pointer font-extrabold">ASR runtime details</summary>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <div>
                    <p class="font-extrabold">Active Models</p>
                    <dl class="mt-2 space-y-1">
                        <div v-for="[label, value] in modelRows" :key="label" class="flex justify-between gap-3">
                            <dt class="font-bold opacity-80">{{ label }}</dt>
                            <dd class="text-right font-semibold">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <p class="font-extrabold">Correction Layer</p>
                    <dl class="mt-2 space-y-1">
                        <div v-for="[label, value] in correctionRows" :key="label" class="flex justify-between gap-3">
                            <dt class="font-bold opacity-80">{{ label }}</dt>
                            <dd class="text-right font-semibold">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </details>
    </section>
</template>
