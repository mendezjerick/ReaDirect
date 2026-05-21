<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AdminDebugPanel from '../../../Components/Admin/AdminDebugPanel.vue';

defineProps({ debug: Object });
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">LLM Debug</h1>
                <p class="mt-1 text-sm font-medium text-muted">Raw LLM interaction debug data and traces</p>
            </div>
        </div>
        <AdminDebugPanel title="LLM Interaction" :data="debug" />
    </AdminLayout>
</template>
