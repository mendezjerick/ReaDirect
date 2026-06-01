<template>
    <div class="anim-bottom-bar fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/60 bg-white/95 px-4 py-3.5 shadow-[0_-8px_30px_-12px_rgba(0,0,0,0.08)] backdrop-blur-lg">
        <div class="learner-frame flex justify-end">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.anim-bottom-bar {
    animation: barSlideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.3s;
    transform: translateY(100%);
}
@keyframes barSlideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>
