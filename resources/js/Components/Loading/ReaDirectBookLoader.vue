<script setup>
defineProps({
    message: { type: String, default: 'Preparing ReaDirect...' },
});
</script>

<template>
    <Teleport to="body">
        <div
            class="readirect-loader"
            role="status"
            aria-live="polite"
            :aria-label="message"
        >
            <div class="readirect-book" aria-hidden="true">
                <div class="readirect-book__cover" />
                <div class="readirect-book__page readirect-book__page--one" />
                <div class="readirect-book__page readirect-book__page--two" />
                <div class="readirect-book__page readirect-book__page--three" />
            </div>
            <p class="readirect-loader__message">{{ message }}</p>
        </div>
    </Teleport>
</template>

<style scoped>
.readirect-loader {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 1.5rem;
    background: #f8fafc;
    color: #00236f;
}

.readirect-book {
    position: relative;
    width: 7rem;
    height: 5rem;
    perspective: 24rem;
}

.readirect-book__cover,
.readirect-book__page {
    position: absolute;
    bottom: 0;
    width: 50%;
    height: 4.5rem;
    border: 0.2rem solid #00236f;
}

.readirect-book__cover {
    left: 0;
    width: 100%;
    height: 4.75rem;
    background: #b6c4ff;
}

.readirect-book__page {
    left: 50%;
    transform-origin: left center;
    background: #ffffff;
    backface-visibility: hidden;
    animation: readirect-page-flip 1.2s ease-in-out infinite;
}

.readirect-book__page--two {
    animation-delay: 0.2s;
}

.readirect-book__page--three {
    animation-delay: 0.4s;
}

.readirect-loader__message {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
}

@keyframes readirect-page-flip {
    0%,
    20% {
        transform: rotateY(0deg);
    }
    70%,
    100% {
        transform: rotateY(-180deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .readirect-book__page {
        animation: none;
    }
}
</style>
