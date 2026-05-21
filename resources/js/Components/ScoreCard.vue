<script setup>
defineProps({
    label:    { type: String,           required: true },
    value:    { type: [String, Number], required: true },
    suffix:   { type: String,           default: '' },
    subtitle: { type: String,           default: 'from last month' },
    icon:     { type: Object,           default: null },
    color:    { type: String,           default: 'blue' },
});

/* Sociafy-style: clean white card, circular colored icon on right, large number.
   Each color variant only affects the icon circle background. */
const scheme = {
    blue:   { iconBg: 'bg-blue-50',    iconText: 'text-blue-500',    iconRing: 'ring-blue-100'    },
    green:  { iconBg: 'bg-emerald-50',  iconText: 'text-emerald-500', iconRing: 'ring-emerald-100' },
    purple: { iconBg: 'bg-violet-50',   iconText: 'text-violet-500',  iconRing: 'ring-violet-100'  },
    orange: { iconBg: 'bg-orange-50',   iconText: 'text-orange-500',  iconRing: 'ring-orange-100'  },
};
</script>

<template>
    <article
        class="group relative rounded-2xl bg-surface border border-border/60 p-5 transition-all duration-200 hover:shadow-lg hover:shadow-black/[0.04] hover:-translate-y-0.5"
    >
        <div class="flex items-center justify-between gap-3">
            <!-- Label + value (left) -->
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted">{{ label }}</p>
                <p class="mt-2 text-3xl font-extrabold leading-none text-text">
                    {{ value }}<span v-if="suffix" class="ml-1 text-base font-semibold text-muted">{{ suffix }}</span>
                </p>
                <!-- Footer slot or subtitle text -->
                <slot name="footer">
                    <p v-if="subtitle" class="mt-1.5 text-[11px] font-medium text-muted">{{ subtitle }}</p>
                </slot>
            </div>

            <!-- Circular icon (right, Sociafy-style) -->
            <div
                v-if="icon"
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full ring-4 transition-transform duration-200 group-hover:scale-105"
                :class="[
                    (scheme[color] ?? scheme.blue).iconBg,
                    (scheme[color] ?? scheme.blue).iconText,
                    (scheme[color] ?? scheme.blue).iconRing,
                ]"
            >
                <component :is="icon" :size="20" />
            </div>
        </div>
    </article>
</template>
