<script setup>
defineProps({ value: { type: [Object, Array, String, Number, Boolean, null], default: null } });

const format = (value) => {
    if (value === null || value === undefined) {
        return 'null';
    }

    return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
};
</script>

<template>
    <pre class="max-h-96 overflow-auto rounded-xl border border-border bg-slate-950 p-4 text-xs leading-relaxed text-slate-100">{{ format(value) }}</pre>
</template>
