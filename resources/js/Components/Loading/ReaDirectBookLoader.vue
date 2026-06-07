<template>
    <div
        class="book-loader-overlay"
        role="status"
        aria-live="polite"
        aria-label="Loading learning agents"
    >
        <div class="book-loader" aria-hidden="true">
            <span class="book-loader__cover" />
            <span class="book-loader__page book-loader__page--one" />
            <span class="book-loader__page book-loader__page--two" />
            <span class="book-loader__page book-loader__page--three" />
        </div>
        <p>Preparing ReaDirect...</p>
    </div>
</template>

<style scoped>
.book-loader-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 1.5rem;
    background: #f8fbff;
    color: #00236f;
    font-size: 1.125rem;
    font-weight: 800;
}

.book-loader {
    position: relative;
    width: 7rem;
    height: 4.5rem;
    perspective: 18rem;
}

.book-loader__cover,
.book-loader__page {
    position: absolute;
    bottom: 0;
    width: 50%;
    height: 4rem;
    transform-origin: right center;
}

.book-loader__cover {
    left: 0;
    width: 100%;
    height: 4.25rem;
    border: 0.3rem solid #2563eb;
    border-radius: 0.45rem;
    background: #dbeafe;
}

.book-loader__page {
    left: 0.25rem;
    border: 0.15rem solid #60a5fa;
    border-radius: 0.25rem 0.5rem 0.5rem 0.25rem;
    background: #ffffff;
    animation: flip-page 1.2s ease-in-out infinite;
}

.book-loader__page--two {
    animation-delay: 0.2s;
}

.book-loader__page--three {
    animation-delay: 0.4s;
}

@keyframes flip-page {
    0%, 20% {
        transform: rotateY(0deg);
    }
    70%, 100% {
        transform: rotateY(-180deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .book-loader__page {
        animation-duration: 2.4s;
    }
}
</style>
