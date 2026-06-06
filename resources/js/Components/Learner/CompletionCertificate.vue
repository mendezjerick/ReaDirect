<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    firstName:    { type: String,           default: 'Student' },
    lastName:     { type: String,           default: '' },
    completedAt:  { type: String,           default: '' },
    readingLevel: { type: String,           default: '' },
    accuracyScore:{ type: [String, Number], default: '' },
    crlaLevel:    { type: String,           default: '' },
});

const certRef = ref(null);
const fullName = computed(() => [props.firstName, props.lastName].filter(Boolean).join(' '));

const formattedDate = computed(() => {
    const d = props.completedAt ? new Date(props.completedAt) : new Date();
    try {
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    } catch { return props.completedAt || ''; }
});

const hasBadges = computed(() => props.readingLevel || props.accuracyScore || props.crlaLevel);

const printCertificate = () => {
    const html = certRef.value.outerHTML;
    const win = window.open('', '_blank', 'width=1100,height=720');
    win.document.write(`<!DOCTYPE html><html><head>
<meta charset="utf-8"/>
<title>Certificate of Completion – ReaDirect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Dancing+Script:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{background:#f5f0e8;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:32px;}
@media print{body{background:#f5f0e8;padding:0;}@page{size:A4 landscape;margin:0;}}
</style>
</head><body>${html}</body></html>`);
    win.document.close();
    setTimeout(() => { win.focus(); win.print(); }, 600);
};
</script>

<template>
    <div class="cert-wrapper">

        <div ref="certRef" class="certificate" aria-label="Certificate of Completion" role="img">

            <!-- ── Double border frame ── -->
            <div class="frame-outer" aria-hidden="true">
                <div class="frame-inner" aria-hidden="true">

                    <!-- Corner ornaments -->
                    <div class="corner-ornament corner-tl" aria-hidden="true">
                        <svg width="52" height="52" viewBox="0 0 52 52" fill="none">
                            <path d="M2 2 L20 2 M2 2 L2 20" stroke="#b8973a" stroke-width="2"/>
                            <path d="M8 8 L18 8 M8 8 L8 18" stroke="#b8973a" stroke-width="1.2"/>
                            <circle cx="2" cy="2" r="2.5" fill="#b8973a"/>
                            <circle cx="13" cy="13" r="1.5" fill="#b8973a" opacity="0.6"/>
                        </svg>
                    </div>
                    <div class="corner-ornament corner-tr" aria-hidden="true">
                        <svg width="52" height="52" viewBox="0 0 52 52" fill="none">
                            <path d="M50 2 L32 2 M50 2 L50 20" stroke="#b8973a" stroke-width="2"/>
                            <path d="M44 8 L34 8 M44 8 L44 18" stroke="#b8973a" stroke-width="1.2"/>
                            <circle cx="50" cy="2" r="2.5" fill="#b8973a"/>
                            <circle cx="39" cy="13" r="1.5" fill="#b8973a" opacity="0.6"/>
                        </svg>
                    </div>
                    <div class="corner-ornament corner-bl" aria-hidden="true">
                        <svg width="52" height="52" viewBox="0 0 52 52" fill="none">
                            <path d="M2 50 L20 50 M2 50 L2 32" stroke="#b8973a" stroke-width="2"/>
                            <path d="M8 44 L18 44 M8 44 L8 34" stroke="#b8973a" stroke-width="1.2"/>
                            <circle cx="2" cy="50" r="2.5" fill="#b8973a"/>
                            <circle cx="13" cy="39" r="1.5" fill="#b8973a" opacity="0.6"/>
                        </svg>
                    </div>
                    <div class="corner-ornament corner-br" aria-hidden="true">
                        <svg width="52" height="52" viewBox="0 0 52 52" fill="none">
                            <path d="M50 50 L32 50 M50 50 L50 32" stroke="#b8973a" stroke-width="2"/>
                            <path d="M44 44 L34 44 M44 44 L44 34" stroke="#b8973a" stroke-width="1.2"/>
                            <circle cx="50" cy="50" r="2.5" fill="#b8973a"/>
                            <circle cx="39" cy="39" r="1.5" fill="#b8973a" opacity="0.6"/>
                        </svg>
                    </div>

                    <!-- ── Content ── -->
                    <div class="cert-content">

                        <!-- ReaDirect brand mark -->
                        <div class="cert-brand" aria-label="ReaDirect">
                            <div class="brand-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 2L3 7l9 5 9-5-9-5z" fill="currentColor"/>
                                    <path d="M3 12l9 5 9-5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M3 17l9 5 9-5" stroke="currentColor" stroke-width="1.5" fill="none" opacity="0.5"/>
                                </svg>
                            </div>
                            <span>ReaDirect</span>
                        </div>

                        <!-- Top divider ornament -->
                        <div class="ornament-row" aria-hidden="true">
                            <div class="orn-line"/>
                            <div class="orn-diamond">◆</div>
                            <div class="orn-line"/>
                        </div>

                        <!-- Main heading -->
                        <h1 class="cert-heading">Certificate of Completion</h1>

                        <!-- Presented text -->
                        <p class="cert-presented">This is to certify that</p>

                        <!-- Student name -->
                        <p class="cert-name">{{ fullName }}</p>

                        <!-- Gold rule -->
                        <div class="gold-rule" aria-hidden="true"/>

                        <!-- Body text -->
                        <p class="cert-body-text">
                            has successfully completed the <em>ReaDirect Reading Assessment Program</em>
                            and demonstrated outstanding dedication throughout their entire reading journey.
                        </p>

                        <!-- Achievement row (if data available) -->
                        <div v-if="hasBadges" class="cert-achievements" role="list">
                            <div v-if="readingLevel" class="achievement-item" role="listitem">
                                <span class="ach-label">Reading Level</span>
                                <span class="ach-value">{{ readingLevel }}</span>
                            </div>
                            <div v-if="readingLevel && (accuracyScore || crlaLevel)" class="ach-dot" aria-hidden="true">·</div>
                            <div v-if="accuracyScore" class="achievement-item" role="listitem">
                                <span class="ach-label">Accuracy</span>
                                <span class="ach-value">{{ accuracyScore }}</span>
                            </div>
                            <div v-if="accuracyScore && crlaLevel" class="ach-dot" aria-hidden="true">·</div>
                            <div v-if="crlaLevel" class="achievement-item" role="listitem">
                                <span class="ach-label">CRLA Level</span>
                                <span class="ach-value">{{ crlaLevel }}</span>
                            </div>
                        </div>

                        <!-- Divider ornament -->
                        <div class="ornament-row ornament-row--thin" aria-hidden="true">
                            <div class="orn-line"/>
                            <div class="orn-diamond orn-diamond--sm">◆</div>
                            <div class="orn-line"/>
                        </div>

                        <!-- Bottom: sig · seal · date -->
                        <div class="cert-footer-row">

                            <!-- Left signature -->
                            <div class="cert-sig-block">
                                <div class="sig-script" aria-hidden="true">
                                    <svg width="110" height="30" viewBox="0 0 110 30" fill="none">
                                        <path d="M5 22 C18 6, 35 24, 55 14 C72 5, 90 20, 105 10"
                                              stroke="#1a2f6e" stroke-width="1.8"
                                              stroke-linecap="round" fill="none"/>
                                        <path d="M55 14 C58 16, 60 18, 55 22"
                                              stroke="#1a2f6e" stroke-width="1.2"
                                              stroke-linecap="round" fill="none"/>
                                    </svg>
                                </div>
                                <div class="sig-line-bar" aria-hidden="true"/>
                                <p class="sig-title">Reading Specialist</p>
                            </div>

                            <!-- Center wax seal -->
                            <div class="wax-seal" aria-label="Official seal" role="img">
                                <svg viewBox="0 0 130 130" aria-hidden="true" class="seal-svg">
                                    <defs>
                                        <radialGradient id="sealOuter" cx="40%" cy="35%">
                                            <stop offset="0%"   stop-color="#f0c040"/>
                                            <stop offset="100%" stop-color="#a07010"/>
                                        </radialGradient>
                                        <radialGradient id="sealInner" cx="35%" cy="30%">
                                            <stop offset="0%"   stop-color="#ffe97a"/>
                                            <stop offset="70%"  stop-color="#d4a017"/>
                                            <stop offset="100%" stop-color="#8a6000"/>
                                        </radialGradient>
                                        <radialGradient id="sealCenter" cx="35%" cy="30%">
                                            <stop offset="0%"   stop-color="#fff3a0"/>
                                            <stop offset="100%" stop-color="#c8920e"/>
                                        </radialGradient>
                                    </defs>

                                    <!-- Jagged outer star burst (16 points) -->
                                    <polygon
                                        points="65,5 70,26 80,10 80,32 93,18 88,39 103,30 93,49 110,45 96,60 113,62 97,73 112,80 94,86 107,97 88,98 98,111 80,107 85,122 68,113 65,128 62,113 45,122 50,107 32,111 42,98 23,97 33,86 15,80 30,73 14,62 31,60 17,45 34,49 24,30 39,39 34,18 47,32 47,10 60,26"
                                        fill="url(#sealOuter)"
                                    />
                                    <!-- Smooth middle ring -->
                                    <circle cx="65" cy="65" r="38" fill="url(#sealInner)"/>
                                    <!-- Inner circle -->
                                    <circle cx="65" cy="65" r="28" fill="url(#sealCenter)"/>
                                    <!-- Star -->
                                    <polygon
                                        points="65,42 70,58 88,58 74,68 79,84 65,74 51,84 56,68 42,58 60,58"
                                        fill="white" opacity="0.9"
                                    />
                                    <!-- Shine -->
                                    <ellipse cx="55" cy="52" rx="9" ry="6" fill="white" opacity="0.25" transform="rotate(-20 55 52)"/>
                                </svg>
                                <p class="seal-label">OFFICIAL</p>
                            </div>

                            <!-- Right: date -->
                            <div class="cert-sig-block cert-sig-block--right">
                                <div class="sig-date" aria-label="Date of completion">{{ formattedDate }}</div>
                                <div class="sig-line-bar" aria-hidden="true"/>
                                <p class="sig-title">Date of Completion</p>
                            </div>

                        </div>
                        <!-- end footer row -->

                    </div>
                    <!-- end cert-content -->

                </div>
            </div>

        </div>
        <!-- end .certificate -->

        <!-- Print button -->
        <div class="cert-actions">
            <button
                id="btn-print-certificate"
                type="button"
                class="print-btn"
                aria-label="Print or save this certificate"
                @click="printCertificate"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print / Save Certificate
            </button>
        </div>

    </div>
</template>

<style scoped>
/* ══════════════════════════════════════════════
   FONTS  (loaded via app.blade.php)
   Cormorant Garamond — elegant serif headings
   Dancing Script     — cursive student name
   ══════════════════════════════════════════════ */

/* ── Wrapper ── */
.cert-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    width: 100%;
}

/* ══════════════════════════════════════════════
   OUTER CERTIFICATE SHELL
   ══════════════════════════════════════════════ */
.certificate {
    width: 100%;
    max-width: 900px;
    font-family: 'Cormorant Garamond', 'Georgia', serif;
    background: #faf7f0;          /* warm cream paper */
    border-radius: 6px;
    box-shadow:
        0 2px 0 #b8973a,          /* bottom gold shadow */
        0 24px 80px -12px rgba(0, 0, 0, 0.22),
        0 8px 32px rgba(0, 0, 0, 0.10);
    position: relative;
}

/* ── Double border frame ── */
.frame-outer {
    border: 3px solid #00236f;
    border-radius: 4px;
    padding: 7px;
    margin: 14px;
}
.frame-inner {
    border: 1.5px solid #b8973a;
    border-radius: 2px;
    padding: 30px 36px 26px;
    position: relative;
}

/* ── Corner ornament positions ── */
.corner-ornament {
    position: absolute;
    width: 52px;
    height: 52px;
    pointer-events: none;
}
.corner-tl { top:  -4px; left:  -4px; }
.corner-tr { top:  -4px; right: -4px; }
.corner-bl { bottom: -4px; left:  -4px; }
.corner-br { bottom: -4px; right: -4px; }

/* ══════════════════════════════════════════════
   CONTENT
   ══════════════════════════════════════════════ */
.cert-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0;
}

/* ── Brand mark ── */
.cert-brand {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 12px;
    font-weight: 800;
    color: #00236f;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 10px;
    opacity: 0.7;
}
.brand-icon {
    color: #00236f;
    display: flex;
}

/* ── Ornament row ── */
.ornament-row {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 75%;
    margin: 6px 0 10px;
}
.ornament-row--thin {
    width: 60%;
    margin: 10px 0 12px;
}
.orn-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(to right, transparent, #b8973a, transparent);
}
.orn-diamond {
    font-size: 10px;
    color: #b8973a;
    line-height: 1;
}
.orn-diamond--sm { font-size: 8px; }

/* ── Main heading ── */
.cert-heading {
    font-family: 'Cormorant Garamond', 'Georgia', serif;
    font-size: clamp(22px, 3.8vw, 36px);
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #00236f;
    line-height: 1.1;
    margin-bottom: 10px;
}

/* ── Presented to text ── */
.cert-presented {
    font-size: clamp(12px, 1.6vw, 15px);
    font-style: italic;
    color: #6b7280;
    letter-spacing: 0.03em;
    margin-bottom: 6px;
}

/* ── Student name ── */
.cert-name {
    font-family: 'Dancing Script', cursive;
    font-size: clamp(30px, 5.5vw, 54px);
    font-weight: 700;
    color: #111827;
    line-height: 1.0;
    letter-spacing: 0.01em;
    margin-bottom: 10px;
}

/* ── Gold rule ── */
.gold-rule {
    width: 70%;
    height: 1px;
    background: linear-gradient(to right, transparent, #b8973a 20%, #d4af37 50%, #b8973a 80%, transparent);
    margin-bottom: 12px;
}

/* ── Body text ── */
.cert-body-text {
    font-size: clamp(11px, 1.5vw, 14px);
    color: #374151;
    line-height: 1.75;
    max-width: 560px;
    letter-spacing: 0.01em;
}
.cert-body-text em {
    font-style: italic;
    color: #00236f;
    font-weight: 600;
}

/* ── Achievement row ── */
.cert-achievements {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 10px;
    flex-wrap: wrap;
}
.achievement-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
}
.ach-label {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #9ca3af;
}
.ach-value {
    font-family: 'Cormorant Garamond', serif;
    font-size: 17px;
    font-weight: 700;
    color: #00236f;
}
.ach-dot {
    font-size: 22px;
    color: #b8973a;
    line-height: 1;
    padding: 0 4px;
    align-self: center;
    margin-top: 8px;
}

/* ══════════════════════════════════════════════
   FOOTER ROW  (sig · seal · date)
   ══════════════════════════════════════════════ */
.cert-footer-row {
    width: 100%;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
}

/* ── Signature blocks ── */
.cert-sig-block {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
}
.cert-sig-block--right {
    align-items: flex-end;
}
.sig-script {
    margin-bottom: -2px;
}
.sig-date {
    font-family: 'Cormorant Garamond', serif;
    font-size: clamp(10px, 1.4vw, 13px);
    font-style: italic;
    color: #374151;
    margin-bottom: 2px;
    text-align: right;
}
.sig-line-bar {
    width: 100%;
    max-width: 140px;
    height: 1px;
    background: linear-gradient(to right, #00236f, #b8973a);
}
.cert-sig-block--right .sig-line-bar {
    background: linear-gradient(to left, #00236f, #b8973a);
}
.sig-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(9px, 1.2vw, 11px);
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #6b7280;
    margin-top: 2px;
}

/* ── Wax seal ── */
.wax-seal {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    margin-bottom: -4px;
}
.seal-svg {
    width: clamp(68px, 9vw, 96px);
    height: clamp(68px, 9vw, 96px);
    filter: drop-shadow(0 4px 12px rgba(176, 135, 0, 0.4));
}
.seal-label {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #b8973a;
}

/* ══════════════════════════════════════════════
   PRINT BUTTON
   ══════════════════════════════════════════════ */
.cert-actions { display: flex; justify-content: center; }
.print-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: linear-gradient(135deg, #00236f, #1e3a8a);
    color: white;
    border: none;
    border-radius: 50px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0, 35, 111, 0.28);
    transition: all 0.2s ease;
    letter-spacing: 0.02em;
}
.print-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 35, 111, 0.38);
}
.print-btn:active { transform: translateY(0); }

/* ══════════════════════════════════════════════
   RESPONSIVE
   ══════════════════════════════════════════════ */
@media (max-width: 600px) {
    .frame-outer  { margin: 10px; padding: 5px; }
    .frame-inner  { padding: 20px 16px 18px; }
    .cert-footer-row { flex-wrap: wrap; justify-content: center; gap: 12px; }
    .wax-seal     { order: -1; }
    .cert-sig-block, .cert-sig-block--right { align-items: center; flex: 0 0 auto; }
    .sig-line-bar { background: linear-gradient(to right, #00236f, #b8973a); }
    .ornament-row { width: 90%; }
}

/* ══════════════════════════════════════════════
   PRINT
   ══════════════════════════════════════════════ */
@media print {
    .cert-actions { display: none; }
    .certificate  { box-shadow: none; max-width: 100%; }
}
</style>
