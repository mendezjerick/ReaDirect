<script setup>
import { computed } from 'vue';
import { Bot, CheckCircle2, AlertTriangle, ExternalLink, ShieldCheck } from 'lucide-vue-next';

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

const architectureLabel = computed(() => (
    props.status?.asr_architecture === 'wav2vec2_only'
        ? 'Wav2Vec2-only runtime'
        : reported(props.status?.asr_architecture)
));

const modelPath = computed(() => (
    props.status?.model_used
    ?? props.status?.wav2vec2_asr_model_name
    ?? props.status?.active_asr_model_path
    ?? props.status?.model_size
));

const activeModelLabel = computed(() => {
    const version = String(props.status?.model_version ?? '').trim();
    const path = String(modelPath.value ?? '').trim();

    if (version === 'letters-v2' || path.includes('wav2vec2-readirect-asr-letters-v2')) {
        return 'Fine-tuned Wav2Vec2 letters-v2';
    }

    return reported(props.status?.active_asr_model ?? props.status?.wav2vec2_asr_model_name ?? path);
});

const phonemeSupportLabel = computed(() => {
    if (props.status?.wav2vec2_phoneme_available === true) return 'Wav2Vec2 phoneme model';
    if (props.status?.wav2vec2_phoneme_available === false) return 'Unavailable';
    return 'Not reported';
});

const whisperRuntimeLabel = computed(() => {
    if (props.status?.whisper_removed === true) return 'Removed from runtime';
    if (props.status?.whisper_removed === false) return 'Reported available';
    return 'Not reported';
});

const details = computed(() => [
    props.status?.base_url ? `URL: ${props.status.base_url}` : null,
    `Architecture: ${architectureLabel.value}`,
    `ASR Model: ${activeModelLabel.value}`,
    `Whisper Runtime: ${whisperRuntimeLabel.value}`,
].filter(Boolean));

const llmStatus = computed(() => props.status?.llm ?? {});
const llmTone = computed(() => {
    if (llmStatus.value?.connected === true) return 'connected';
    if (llmStatus.value?.status === 'disabled') return 'disabled';
    return 'unavailable';
});

const llmStyles = computed(() => ({
    connected: 'border-emerald-200 bg-white/65 text-emerald-900',
    disabled: 'border-amber-200 bg-white/65 text-amber-900',
    unavailable: 'border-red-200 bg-white/65 text-red-900',
}[llmTone.value]));

const llmBadgeStyles = computed(() => ({
    connected: 'bg-emerald-100 text-emerald-800',
    disabled: 'bg-amber-100 text-amber-800',
    unavailable: 'bg-red-100 text-red-800',
}[llmTone.value]));

const llmRows = computed(() => [
    ['Provider', reported(llmStatus.value?.provider)],
    ['Base URL', reported(llmStatus.value?.base_url)],
    ['Configured Model', reported(llmStatus.value?.model)],
    ['Installed Models', reported(llmStatus.value?.installed_models)],
]);

const modelRows = computed(() => [
    ['Active ASR Architecture', architectureLabel.value],
    ['Active ASR Model', activeModelLabel.value],
    ['Model Path', reported(modelPath.value)],
    ['Base Model', reported(props.status?.base_model)],
    ['Phoneme Support', phonemeSupportLabel.value],
    ['Phoneme Model Path', reported(props.status?.wav2vec2_phoneme_model_name)],
    ['Whisper Runtime', whisperRuntimeLabel.value],
    ['Supported Prompt Types', reported(props.status?.supported_prompt_types)],
]);

const correctionRows = computed(() => [
    ['Correction Layer', reported(props.status?.correction_layer_enabled)],
    ['Expected-centric Scoring', reported(props.status?.expected_centric_scoring_enabled)],
    ['Phoneme Evidence', reported(props.status?.phoneme_evidence_enabled)],
    ['Reinforcement Correction Memory', reported(props.status?.reinforcement_corrections_enabled)],
    ['Audio Quality Validation', reported(props.status?.audio_quality_validation_enabled)],
    ['Pause Detection', reported(props.status?.pause_detection_enabled)],
    ['Retry / Uncertainty Decision', reported(props.status?.uncertainty_decision_enabled)],
    ['Thresholds', reported(props.status?.thresholds)],
    ['Audio Quality Thresholds', reported(props.status?.audio_quality_thresholds)],
    ['Loaded Model Paths', reported(props.status?.local_model_paths_loaded)],
]);

const reinforcementEnabled = computed(() => props.status?.reinforcement_corrections_enabled === true);
const reinforcementLetters = computed(() => Number(props.status?.reinforcement_letter_rules_count ?? 0));
const reinforcementWords = computed(() => Number(props.status?.reinforcement_word_rules_count ?? 0));
const reinforcementWarnings = computed(() => props.status?.reinforcement_load_warnings ?? []);
const reinforcementFiles = computed(() => props.status?.reinforcement_files_loaded ?? []);
const reinforcementWorking = computed(() => (
    reinforcementEnabled.value
    && reinforcementLetters.value > 0
    && reinforcementWarnings.value.length === 0
));

const reinforcementSummary = computed(() => {
    if (!reinforcementEnabled.value) {
        return 'Reinforcement correction memory is disabled.';
    }

    if (reinforcementWorking.value) {
        return 'Reinforcement correction memory is working for letters. Word correction memory is prepared for future CSV rules.';
    }

    if (reinforcementWarnings.value.length > 0) {
        return 'Reinforcement correction memory needs attention before it can be trusted.';
    }

    return 'Reinforcement correction memory is enabled, but no letter rules are loaded yet.';
});
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
                            class="max-w-full break-words rounded-md bg-white/60 px-2 py-1 text-[11px] font-bold"
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

        <div class="mt-3 rounded-lg border p-3 text-xs" :class="llmStyles">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div class="flex min-w-0 gap-2">
                    <Bot :size="16" class="mt-0.5 shrink-0" />
                    <div class="min-w-0">
                        <p class="font-extrabold">{{ llmStatus?.label ?? 'LLM status unavailable' }}</p>
                        <p class="mt-1 break-words font-semibold leading-relaxed opacity-90">
                            {{ llmStatus?.message ?? 'Miss Ciel local coaching status could not be checked.' }}
                        </p>
                    </div>
                </div>
                <span class="inline-flex w-fit shrink-0 items-center rounded-md px-2 py-1 text-[11px] font-extrabold" :class="llmBadgeStyles">
                    {{ llmStatus?.connected ? 'Connected' : (llmStatus?.status === 'disabled' ? 'Disabled' : 'Needs attention') }}
                </span>
            </div>
            <dl class="mt-3 grid gap-2 md:grid-cols-2">
                <div v-for="[label, value] in llmRows" :key="label" class="grid min-w-0 gap-1 rounded-md bg-white/45 px-2 py-1.5">
                    <dt class="font-bold opacity-80">{{ label }}</dt>
                    <dd class="min-w-0 break-words font-semibold">{{ value }}</dd>
                </div>
            </dl>
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
                        <div v-for="[label, value] in modelRows" :key="label" class="grid min-w-0 gap-1 sm:grid-cols-[minmax(8rem,0.8fr)_minmax(0,1.2fr)]">
                            <dt class="font-bold opacity-80">{{ label }}</dt>
                            <dd class="min-w-0 break-words font-semibold sm:text-right">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
                <div>
                    <p class="font-extrabold">Correction Layer</p>
                    <dl class="mt-2 space-y-1">
                        <div v-for="[label, value] in correctionRows" :key="label" class="grid min-w-0 gap-1 sm:grid-cols-[minmax(8rem,0.75fr)_minmax(0,1.25fr)]">
                            <dt class="font-bold opacity-80">{{ label }}</dt>
                            <dd class="min-w-0 break-words font-semibold sm:text-right">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="mt-4 rounded-lg border border-emerald-200 bg-white/60 p-3">
                <div class="flex items-center gap-2">
                    <ShieldCheck :size="15" class="text-emerald-700" />
                    <p class="font-extrabold">Reinforcement Correction Memory</p>
                </div>
                <div class="mt-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <p class="max-w-3xl font-semibold leading-relaxed">{{ reinforcementSummary }}</p>
                    <span
                        class="inline-flex w-fit items-center rounded-md px-2 py-1 text-[11px] font-extrabold"
                        :class="reinforcementWorking ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                        {{ reinforcementWorking ? 'Working' : 'Needs attention' }}
                    </span>
                </div>
                <p class="mt-2 text-[11px] font-semibold opacity-80">
                    Current coverage: letters {{ reinforcementLetters }} rules loaded, words {{ reinforcementWords }} rules loaded.
                    <span v-if="reinforcementFiles.length"> Source: {{ reinforcementFiles.join(', ') }}.</span>
                </p>
                <p v-if="reinforcementWarnings.length" class="mt-2 text-[11px] font-bold text-amber-800">
                    {{ reinforcementWarnings.join(' ') }}
                </p>
            </div>
        </details>
    </section>
</template>
