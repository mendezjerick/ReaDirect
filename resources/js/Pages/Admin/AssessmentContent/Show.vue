<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
import { Pencil, FileQuestion, Hash } from 'lucide-vue-next';

defineProps({ item: Object, usageCount: Number });
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">{{ item.title }}</h1>
                <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-muted">
                    <Hash class="size-4" />
                    {{ item.content_type }} &bull; used {{ usageCount }} times
                </p>
            </div>
            <Link :href="`/admin/assessment-content/${item.id}/edit`" class="group inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                <Pencil class="size-4" />
                Edit Content
            </Link>
        </div>

        <div class="grid gap-6">
            <DashboardCard class="in-card" style="--delay: 0ms">
                <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-500">
                        <FileQuestion class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Content Details</h2>
                </div>
                <div class="rounded-xl border border-border/40 bg-surface p-1">
                    <AdminJsonViewer :value="item" />
                </div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.in-card { animation: entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--delay, 0ms); }
@keyframes entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
