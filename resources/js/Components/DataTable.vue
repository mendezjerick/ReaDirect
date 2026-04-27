<script setup>
defineProps({ headers: { type: Array, default: () => [] }, rows: { type: Array, default: () => [] } });
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-border/60 bg-surface">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border/60 bg-background">
                    <th v-for="header in headers" :key="header" class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">{{ header }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index" class="border-t border-border/40 transition-colors hover:bg-background/60">
                    <td v-for="header in headers" :key="header" class="px-4 py-3 text-[13px] text-slate-600">{{ row[header] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
