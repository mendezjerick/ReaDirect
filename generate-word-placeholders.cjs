const fs = require('fs');
const path = require('path');

const outputDir = path.resolve(__dirname, '..', 'ReaDirect-IA', 'assets', 'images', 'reading', 'words');

const words = {
    ball:  { emoji: '⚽', bg: ['#FF6B6B','#EE5A24'] },
    bat:   { emoji: '🦇', bg: ['#6C5CE7','#A29BFE'] },
    bed:   { emoji: '🛏️', bg: ['#74B9FF','#0984E3'] },
    bee:   { emoji: '🐝', bg: ['#FFEAA7','#FDCB6E'] },
    bit:   { emoji: '🔹', bg: ['#55E6C1','#1ABC9C'] },
    book:  { emoji: '📖', bg: ['#A29BFE','#6C5CE7'] },
    box:   { emoji: '📦', bg: ['#F8B739','#F39C12'] },
    bug:   { emoji: '🐛', bg: ['#A3DE83','#27AE60'] },
    car:   { emoji: '🚗', bg: ['#FF6348','#E74C3C'] },
    cat:   { emoji: '🐱', bg: ['#FFB8B8','#FF6B6B'] },
    cup:   { emoji: '☕', bg: ['#DFE6E9','#B2BEC3'] },
    dog:   { emoji: '🐶', bg: ['#FFEAA7','#E17055'] },
    duck:  { emoji: '🦆', bg: ['#81ECEC','#00CEC9'] },
    fish:  { emoji: '🐟', bg: ['#74B9FF','#0984E3'] },
    hand:  { emoji: '✋', bg: ['#FFEAA7','#E17055'] },
    hat:   { emoji: '🎩', bg: ['#636E72','#2D3436'] },
    hop:   { emoji: '🐰', bg: ['#FD79A8','#E84393'] },
    lip:   { emoji: '👄', bg: ['#FD79A8','#E84393'] },
    man:   { emoji: '👨', bg: ['#74B9FF','#0984E3'] },
    moon:  { emoji: '🌙', bg: ['#2D3436','#636E72'] },
    pan:   { emoji: '🍳', bg: ['#DFE6E9','#636E72'] },
    pen:   { emoji: '🖊️', bg: ['#0984E3','#74B9FF'] },
    pet:   { emoji: '🐾', bg: ['#E17055','#FFEAA7'] },
    pin:   { emoji: '📌', bg: ['#E74C3C','#FF6348'] },
    red:   { emoji: '🔴', bg: ['#FF6B6B','#EE5A24'] },
    rock:  { emoji: '🪨', bg: ['#B2BEC3','#636E72'] },
    run:   { emoji: '🏃', bg: ['#55E6C1','#00B894'] },
    sit:   { emoji: '🪑', bg: ['#F8B739','#E17055'] },
    sun:   { emoji: '☀️', bg: ['#FFEAA7','#F39C12'] },
    tap:   { emoji: '💧', bg: ['#74B9FF','#0984E3'] },
    top:   { emoji: '🔝', bg: ['#A29BFE','#6C5CE7'] },
    tree:  { emoji: '🌳', bg: ['#A3DE83','#27AE60'] },
};

function makeSvg(word, { emoji, bg }) {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="${bg[0]}"/>
      <stop offset="100%" stop-color="${bg[1]}"/>
    </linearGradient>
    <filter id="shadow">
      <feDropShadow dx="0" dy="4" stdDeviation="6" flood-opacity="0.15"/>
    </filter>
  </defs>
  <rect width="320" height="320" rx="48" fill="url(#bg)"/>
  <rect x="24" y="24" width="272" height="272" rx="36" fill="white" fill-opacity="0.2"/>
  <text x="160" y="155" text-anchor="middle" font-size="120" filter="url(#shadow)">${emoji}</text>
  <rect x="60" y="235" width="200" height="52" rx="26" fill="white" fill-opacity="0.85"/>
  <text x="160" y="270" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif" font-size="28" font-weight="900" fill="#334155" letter-spacing="1">${word}</text>
</svg>`;
}

if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
}

for (const [word, config] of Object.entries(words)) {
    const svg = makeSvg(word, config);
    fs.writeFileSync(path.join(outputDir, `${word}.svg`), svg, 'utf8');
}

console.log(`Generated ${Object.keys(words).length} word placeholder SVGs in ${outputDir}`);
