<template>
    <div class="min-h-screen bg-background text-text md:flex">
        <aside class="border-b border-border bg-surface p-4 md:min-h-screen md:w-64 md:border-b-0 md:border-r">
            <a href="/teacher/dashboard" class="text-xl font-black text-primary">ReaDirect</a>
            <nav class="mt-8 grid gap-2 text-sm font-semibold text-muted">
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="/teacher/dashboard">Dashboard</a>
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="#">Learners</a>
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="#">Reports</a>
            </nav>
        </aside>
        <main class="flex-1 p-4 md:p-8">
            <slot />
        </main>
    </div>
</template>
