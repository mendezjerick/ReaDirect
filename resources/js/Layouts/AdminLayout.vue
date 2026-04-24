<template>
    <div class="min-h-screen bg-background text-text md:flex">
        <aside class="border-b border-border bg-surface p-4 md:min-h-screen md:w-72 md:border-b-0 md:border-r">
            <a href="#" class="text-xl font-black text-primary">ReaDirect Admin</a>
            <nav class="mt-8 grid gap-2 text-sm font-semibold text-muted">
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="#">Schools</a>
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="#">Content</a>
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="#">Rules</a>
                <a class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" href="#">Audit Logs</a>
            </nav>
        </aside>
        <main class="flex-1 p-4 md:p-8">
            <slot />
        </main>
    </div>
</template>
