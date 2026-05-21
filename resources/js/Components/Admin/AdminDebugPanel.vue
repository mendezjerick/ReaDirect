<script setup>
import AdminJsonViewer from './AdminJsonViewer.vue';
import DashboardCard from '../DashboardCard.vue';
import { Terminal, Copy, Check } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps({ title: String, data: Object });
const copied = ref(false);

const copyToClipboard = async () => {
    try {
        const textToCopy = typeof props.data === 'string' ? props.data : JSON.stringify(props.data, null, 2);
        await navigator.clipboard.writeText(textToCopy);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
};
</script>

<template>
    <DashboardCard class="dbg-card-in">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border/60 pb-4">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-slate-300 shadow-sm shadow-slate-900/20">
                    <Terminal class="size-4" />
                </div>
                <div>
                    <h2 class="text-[15px] font-bold text-text">{{ title }}</h2>
                    <p class="text-xs font-medium text-muted">JSON payload</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest text-rose-600 ring-1 ring-rose-500/20">
                    Admin Debug Only
                </span>
                
                <button 
                    @click="copyToClipboard"
                    class="group flex items-center justify-center gap-1.5 rounded-lg border border-border/60 bg-background px-3 py-1.5 text-xs font-semibold text-text transition-all duration-200 hover:bg-slate-100 active:scale-[0.97]"
                >
                    <Check v-if="copied" class="size-3.5 text-emerald-500" />
                    <Copy v-else class="size-3.5 text-slate-400 group-hover:text-slate-600 transition-colors" />
                    {{ copied ? 'Copied!' : 'Copy raw' }}
                </button>
            </div>
        </div>
        
        <div class="rounded-xl overflow-hidden shadow-inner shadow-black/10 ring-1 ring-slate-800">
            <AdminJsonViewer :value="data" />
        </div>
    </DashboardCard>
</template>

<style scoped>
.dbg-card-in { animation: dbg-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes dbg-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
