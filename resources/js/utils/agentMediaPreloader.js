import { getAllAgentMediaUrls } from './agentMedia';

const preloadedUrls = new Set();
let preloadPromise = null;
let preloadCompleted = false;

const waitWithTimeout = (register, timeoutMs) => new Promise((resolve) => {
    let settled = false;
    const finish = (result) => {
        if (settled) return;
        settled = true;
        window.clearTimeout(timer);
        resolve(result);
    };
    const timer = window.setTimeout(() => finish(false), timeoutMs);
    register(finish);
});

export const preloadImage = (src, timeoutMs = 10000) => {
    if (preloadedUrls.has(src)) return Promise.resolve(true);

    return waitWithTimeout((finish) => {
        const image = new Image();
        image.onload = () => {
            preloadedUrls.add(src);
            finish(true);
        };
        image.onerror = () => finish(false);
        image.src = src;
    }, timeoutMs);
};

export const preloadVideo = (src, timeoutMs = 10000) => {
    if (preloadedUrls.has(src)) return Promise.resolve(true);

    return waitWithTimeout((finish) => {
        const video = document.createElement('video');
        const ready = () => {
            preloadedUrls.add(src);
            finish(true);
        };

        video.preload = 'auto';
        video.muted = true;
        video.playsInline = true;
        video.addEventListener('loadeddata', ready, { once: true });
        video.addEventListener('canplay', ready, { once: true });
        video.addEventListener('error', () => finish(false), { once: true });
        video.src = src;
        video.load();
    }, timeoutMs);
};

const runPreload = async () => {
    const urls = getAllAgentMediaUrls();
    const jobs = urls.map(async (url) => {
        const loaded = /\.(png|jpe?g|webp|gif)(?:[?#]|$)/i.test(url)
            ? await preloadImage(url)
            : await preloadVideo(url);

        if (!loaded && import.meta.env.DEV) {
            console.warn(`[ReaDirect agents] Could not preload ${url}`);
        }

        return { url, loaded };
    });

    return Promise.allSettled(jobs);
};

export const preloadAgentMedia = () => {
    if (preloadCompleted) return Promise.resolve([]);
    if (preloadPromise) return preloadPromise;

    preloadPromise = Promise.race([
        runPreload(),
        new Promise((resolve) => window.setTimeout(() => resolve([]), 18000)),
    ]).finally(() => {
        preloadCompleted = true;
    });

    return preloadPromise;
};

export const isAgentMediaPreloaded = () => preloadCompleted;
