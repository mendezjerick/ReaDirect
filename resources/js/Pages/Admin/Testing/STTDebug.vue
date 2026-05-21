<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AdminDebugPanel from '../../../Components/Admin/AdminDebugPanel.vue';

defineProps({ debug: Object });
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">STT Debug</h1>
                <p class="mt-1 text-sm font-medium text-muted">Raw Speech-to-Text interaction debug data and traces</p>
            </div>
        </div>
        <AdminDebugPanel title="Speech-to-Text Process" :data="debug" />
    </AdminLayout>
</template>
