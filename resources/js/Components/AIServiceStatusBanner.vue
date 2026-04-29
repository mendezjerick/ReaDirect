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

const details = computed(() => [
    props.status?.base_url ? `URL: ${props.status.base_url}` : null,
    props.status?.asr_provider ? `ASR: ${props.status.asr_provider}` : null,
    props.status?.model_size ? `Model: ${props.status.model_size}` : null,
].filter(Boolean));
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
    </section>
</template>
