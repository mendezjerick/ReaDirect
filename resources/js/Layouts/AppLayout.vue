<script setup>
defineProps({ title: { type: String, default: 'ReaDirect' } });
</script>

<template>
    <div class="min-h-screen bg-background text-text">
        <header class="border-b border-border bg-surface">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <a href="/" class="text-2xl font-black text-primary">ReaDirect</a>
                <slot name="nav" />
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-6">
            <slot />
        </main>
    </div>
</template>
