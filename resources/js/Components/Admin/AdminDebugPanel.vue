<script setup>
import AdminJsonViewer from './AdminJsonViewer.vue';

defineProps({ title: String, data: Object });
</script>

<template>
    <section class="rounded-2xl border border-primary/20 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-black text-text">{{ title }}</h2>
            <span class="rounded-full bg-primary-light px-3 py-1 text-xs font-black uppercase tracking-wide text-primary">Admin debug only</span>
        </div>
        <AdminJsonViewer :value="data" />
    </section>
</template>
