<script setup>
defineProps({ headers: { type: Array, default: () => [] }, rows: { type: Array, default: () => [] } });
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-border bg-surface">
        <table class="w-full text-left text-sm">
            <thead class="bg-primary-light text-primary">
                <tr>
                    <th v-for="header in headers" :key="header" class="px-4 py-3 font-black">{{ header }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in rows" :key="index" class="border-t border-border">
                    <td v-for="header in headers" :key="header" class="px-4 py-3 text-muted">{{ row[header] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
