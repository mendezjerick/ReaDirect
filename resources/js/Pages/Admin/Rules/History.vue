<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { ArrowLeft, Clock, FileText } from 'lucide-vue-next';

defineProps({ events: Array });
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Rule History</h1>
                <p class="mt-1 text-sm font-medium text-muted">Audit trail for rule and threshold changes.</p>
            </div>
            <Link href="/admin/rules" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to Rules
            </Link>
        </div>

        <DashboardCard class="rh-card-in">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                        <Clock class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Change Log</h2>
                </div>
                <StatusBadge v-if="events?.length" :status="`${events.length} events`" />
            </div>

            <EmptyState v-if="!events || events.length === 0" title="No history found" message="No rule change history is available yet." />

            <div v-else class="divide-y divide-border/60">
                <div v-for="(event, index) in events" :key="event.id ?? index" class="flex flex-col gap-1 py-3.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between rh-row-in" :style="{ '--row-delay': `${index * 40}ms` }">
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <FileText class="size-3.5" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-text truncate">{{ event.description ?? event.event ?? event.action ?? 'Change' }}</p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium truncate">
                                {{ event.user?.name ?? event.user_name ?? '—' }}
                                <span v-if="event.created_at" class="text-border mx-1">·</span>
                                {{ event.created_at ?? '' }}
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0 pl-11 sm:pl-0">
                        <StatusBadge v-if="event.type ?? event.event_type" :status="event.type ?? event.event_type" />
                    </div>
                </div>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.rh-card-in { animation: rh-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes rh-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.rh-row-in { animation: rh-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes rh-row { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: translateX(0); } }
</style>
