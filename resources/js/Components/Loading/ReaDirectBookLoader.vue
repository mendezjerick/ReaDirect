<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const activeDot = ref(0);
let dotTimer = null;

onMounted(() => {
    dotTimer = setInterval(() => {
        activeDot.value = (activeDot.value + 1) % 6;
    }, 450);
});

onBeforeUnmount(() => clearInterval(dotTimer));
</script>

<template>
    <div class="rl-screen" role="status" aria-label="Preparing ReaDirect">

        <!-- ── Cloud bottom-left ── -->
        <svg class="rl-cloud rl-cloud--bl" viewBox="0 0 600 220" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMinYMax meet">
            <ellipse cx="80"  cy="220" rx="160" ry="100" fill="white" opacity=".95"/>
            <ellipse cx="220" cy="210" rx="180" ry="110" fill="white" opacity=".95"/>
            <ellipse cx="380" cy="215" rx="160" ry="95"  fill="white" opacity=".95"/>
            <ellipse cx="520" cy="220" rx="130" ry="85"  fill="white" opacity=".95"/>
            <rect x="0" y="160" width="600" height="60" fill="white"/>
        </svg>

        <!-- ── Cloud bottom-right ── -->
        <svg class="rl-cloud rl-cloud--br" viewBox="0 0 520 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMaxYMax meet">
            <ellipse cx="440" cy="200" rx="150" ry="95"  fill="white" opacity=".95"/>
            <ellipse cx="300" cy="195" rx="170" ry="100" fill="white" opacity=".95"/>
            <ellipse cx="150" cy="200" rx="140" ry="90"  fill="white" opacity=".95"/>
            <ellipse cx="30"  cy="200" rx="100" ry="75"  fill="white" opacity=".95"/>
            <rect x="0" y="155" width="520" height="45" fill="white"/>
        </svg>

        <!-- ── Sparkles ── -->
        <span class="rl-spark" style="top:16%;left:19%;font-size:14px;animation-delay:0s"    aria-hidden="true">✦</span>
        <span class="rl-spark" style="top:13%;right:22%;font-size:10px;animation-delay:.6s"  aria-hidden="true">✦</span>
        <span class="rl-spark" style="top:30%;left:27%;font-size:9px;animation-delay:1.2s"   aria-hidden="true">✦</span>
        <span class="rl-spark" style="top:26%;right:29%;font-size:13px;animation-delay:.35s" aria-hidden="true">✦</span>
        <span class="rl-spark" style="top:55%;right:19%;font-size:10px;animation-delay:1.0s" aria-hidden="true">✦</span>
        <span class="rl-spark" style="top:60%;left:23%;font-size:8px;animation-delay:.8s"    aria-hidden="true">✦</span>

        <!-- ── Floating circles ── -->
        <span class="rl-circle" style="top:22%;left:14%;width:14px;height:14px" aria-hidden="true"/>
        <span class="rl-circle" style="top:37%;right:15%;width:11px;height:11px;opacity:.45" aria-hidden="true"/>
        <span class="rl-circle" style="top:60%;right:25%;width:13px;height:13px;opacity:.55" aria-hidden="true"/>

        <!-- ── Book ── -->
        <div class="rl-scene" aria-hidden="true">
            <div class="rl-book">

                <!-- spine bump at the bottom center -->
                <div class="rl-book__spine"></div>

                <!-- book face (the stacked pages) -->
                <div class="rl-book__face">

                    <!-- left page -->
                    <div class="rl-book__left"></div>

                    <!-- right page -->
                    <div class="rl-book__right"></div>

                    <!-- animated flip pages (staggered for seamless loop) -->
                    <div class="rl-book__flip rl-book__flip--1"></div>
                    <div class="rl-book__flip rl-book__flip--2"></div>
                    <div class="rl-book__flip rl-book__flip--3"></div>
                    <div class="rl-book__flip rl-book__flip--4"></div>
                    <div class="rl-book__flip rl-book__flip--5"></div>

                </div>
            </div>

            <!-- elliptical shadow underneath -->
            <div class="rl-book__shadow"/>
        </div>

        <!-- ── Text ── -->
        <p class="rl-title">Preparing ReaDirect...</p>
        <p class="rl-sub">Setting up your reading journey</p>

        <!-- ── Dots ── -->
        <div class="rl-dots" aria-hidden="true">
            <span
                v-for="i in 6"
                :key="i"
                class="rl-dot"
                :class="{ 'rl-dot--on': activeDot === i - 1 }"
            />
        </div>

    </div>
</template>

<style scoped>
/* ══════════════════════════════
   SCREEN
══════════════════════════════ */
.rl-screen {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: radial-gradient(ellipse 80% 60% at 50% 45%, #DDE9FF 0%, #EBF2FF 45%, #F0F5FF 100%);
    overflow: hidden;
    animation: rl-screen-in .35s ease both;
}
@keyframes rl-screen-in {
    from { opacity: 0; }
    to   { opacity: 1; }
}

/* ══════════════════════════════
   CLOUDS
══════════════════════════════ */
.rl-cloud {
    position: absolute;
    bottom: 0;
    pointer-events: none;
    display: block;
}
.rl-cloud--bl {
    left: -40px;
    width: clamp(260px, 48vw, 580px);
}
.rl-cloud--br {
    right: -30px;
    width: clamp(220px, 42vw, 500px);
}

/* ══════════════════════════════
   SPARKLES
══════════════════════════════ */
.rl-spark {
    position: absolute;
    pointer-events: none;
    color: #93C5FD;
    font-weight: 900;
    line-height: 1;
    animation: rl-spark-pulse 2.8s ease-in-out infinite;
}
@keyframes rl-spark-pulse {
    0%, 100% { opacity: .45; transform: scale(1);    }
    50%       { opacity: 1;   transform: scale(1.4); }
}

/* ══════════════════════════════
   FLOATING CIRCLES
══════════════════════════════ */
.rl-circle {
    position: absolute;
    border-radius: 50%;
    background: #93C5FD;
    pointer-events: none;
    display: block;
}

/* ══════════════════════════════
   BOOK SCENE
══════════════════════════════ */
.rl-scene {
    perspective: 900px;
    perspective-origin: 50% 20%;
    margin-bottom: 36px;
}

.rl-book {
    position: relative;
    width: 280px;
    height: 160px;
    transform: rotateX(65deg) rotateY(0deg);
    transform-style: preserve-3d;
    animation: rl-float 3.2s ease-in-out infinite;
}
@keyframes rl-float {
    0%, 100% { transform: rotateX(65deg) rotateY(0deg) translateY(0);    }
    50%       { transform: rotateX(65deg) rotateY(0deg) translateY(-12px); }
}

/* rounded spine at the bottom center, like the reference */
.rl-book__spine {
    position: absolute;
    bottom: -18px;
    left: 50%;
    transform: translateX(-50%);
    width: 44px;
    height: 24px;
    background: #1E3A8A; /* Dark blue spine */
    border-radius: 0 0 22px 22px;
    z-index: 0;
    box-shadow: 0 6px 16px rgba(30,64,175,.4);
}

/* main face container (transparent, no tray) */
.rl-book__face {
    position: absolute;
    inset: 0;
    display: flex;
    transform-style: preserve-3d;
    z-index: 1;
}

/* left page */
.rl-book__left {
    flex: 1;
    background: #FFFFFF;
    border-radius: 12px 0 0 12px;
    border-bottom: 12px solid #E2E8F0; /* paper thickness */
    box-shadow: 0 14px 0 #2563EB; /* thick blue cover */
}

/* right page */
.rl-book__right {
    flex: 1;
    background: linear-gradient(135deg, #F0F7FF 0%, #FFFFFF 100%);
    border-radius: 0 12px 12px 0;
    border-bottom: 12px solid #E2E8F0; /* paper thickness */
    box-shadow: 0 14px 0 #2563EB; /* thick blue cover */
}



.rl-book__flip {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 50%;
    background: linear-gradient(to right, #F4F8FF 0%, #ffffff 100%);
    transform-origin: left center;
    transform-style: preserve-3d;
    border-radius: 0 12px 12px 0;
    border-bottom: 12px solid #E2E8F0;
    animation: rl-flip 2.5s cubic-bezier(0.645, 0.045, 0.355, 1) infinite;
    opacity: 0;
}
.rl-book__flip--1 { animation-delay: 0s; }
.rl-book__flip--2 { animation-delay: 0.5s; }
.rl-book__flip--3 { animation-delay: 1.0s; }
.rl-book__flip--4 { animation-delay: 1.5s; }
.rl-book__flip--5 { animation-delay: 2.0s; }

@keyframes rl-flip {
    0%   { transform: rotateY(0deg);    opacity: 0; z-index: 1; box-shadow: none; }
    2%   { transform: rotateY(0deg);    opacity: 1; z-index: 2; box-shadow: none; }
    50%  { transform: rotateY(-90deg);  opacity: 1; z-index: 10; box-shadow: -10px 0 20px rgba(37,99,235,.16); }
    98%  { transform: rotateY(-180deg); opacity: 1; z-index: 2; box-shadow: none; }
    100% { transform: rotateY(-180deg); opacity: 0; z-index: 1; box-shadow: none; }
}

/* shadow under book */
.rl-book__shadow {
    width: 220px;
    height: 22px;
    margin: 10px auto 0;
    background: radial-gradient(ellipse at center, rgba(37,99,235,.2) 0%, transparent 70%);
    animation: rl-shadow 3.2s ease-in-out infinite;
}
@keyframes rl-shadow {
    0%, 100% { transform: scaleX(1);    opacity: 1;   }
    50%       { transform: scaleX(.78); opacity: .55; }
}

/* ══════════════════════════════
   TEXT
══════════════════════════════ */
.rl-title {
    font-size: clamp(1.375rem, 3.5vw, 1.875rem);
    font-weight: 800;
    color: #1E3A8A;
    letter-spacing: -0.025em;
    margin: 0 0 8px;
    text-align: center;
}
.rl-sub {
    font-size: clamp(.875rem, 2vw, 1rem);
    font-weight: 500;
    color: #8EA2C2;
    margin: 0 0 22px;
    text-align: center;
}

/* ══════════════════════════════
   DOTS
══════════════════════════════ */
.rl-dots {
    display: flex;
    gap: 9px;
    align-items: center;
}
.rl-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #BFDBFE;
    transition: background .25s ease, transform .25s ease;
}
.rl-dot--on {
    background: #2563EB;
    transform: scale(1.3);
}

/* ══════════════════════════════
   RESPONSIVE
══════════════════════════════ */
@media (max-width: 520px) {
    .rl-book       { width: 230px; height: 142px; }
    .rl-book__spine { bottom: -14px; width: 34px; height: 18px; border-radius: 0 0 17px 17px; }
    .rl-book__left  { border-radius: 8px 0 0 8px; border-bottom-width: 10px; box-shadow: 0 10px 0 #2563EB; }
    .rl-book__right { border-radius: 0 8px 8px 0; border-bottom-width: 10px; box-shadow: 0 10px 0 #2563EB; }
    .rl-book__flip  { border-radius: 0 8px 8px 0; border-bottom-width: 10px; }
    .rl-scene       { margin-bottom: 28px; }
    .rl-spark       { display: none; }
    .rl-circle      { display: none; }
}

/* ══════════════════════════════
   REDUCED MOTION
══════════════════════════════ */
@media (prefers-reduced-motion: reduce) {
    .rl-book,
    .rl-book__shadow { animation: none; }
    .rl-book__flip   { animation: none; }
    .rl-spark        { animation: none; }
    .rl-screen       { animation: none; }
    .rl-dot          { transition: none; }
}
</style>
