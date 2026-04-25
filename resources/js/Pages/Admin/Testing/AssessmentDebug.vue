<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AdminDebugPanel from '../../../Components/Admin/AdminDebugPanel.vue';
defineProps({ debug: Object });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Assessment Debug</h1><AdminDebugPanel title="Assessment attempt debug data" :data="debug" /></AdminLayout></template>
