<template>
    <div class="anim-bottom-bar fixed inset-x-0 bottom-0 z-30 border-t border-[rgba(54,83,101,0.12)] bg-[rgba(255,253,247,0.94)] px-4 py-3.5 shadow-[0_-10px_26px_rgba(35,55,70,0.10)] backdrop-blur-lg">
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
