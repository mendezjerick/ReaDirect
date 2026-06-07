import { getAllAgentMedia } from '../utils/agentMedia.js';

const VIDEO_TIMEOUT_MS = 10_000;
const IMAGE_TIMEOUT_MS = 8_000;
const GLOBAL_TIMEOUT_MS = 18_000;

const preloadedUrls = new Set();
const preloadedVideoElements = new Map();
let preloadPromise = null;

const withTimeout = (executor, timeoutMs, url) => new Promise((resolve, reject) => {
    let settled = false;

    const finish = (callback, value) => {
        if (settled) {
            return;
        }

        settled = true;
        window.clearTimeout(timeoutId);
        callback(value);
    };

    const timeoutId = window.setTimeout(
        () => finish(reject, new Error(`Timed out preloading ${url}`)),
        timeoutMs,
    );

    executor(
        () => finish(resolve, url),
        (error) => finish(reject, error instanceof Error ? error : new Error(`Failed to preload ${url}`)),
    );
});

export const preloadVideo = (src, timeoutMs = VIDEO_TIMEOUT_MS) => {
    if (preloadedUrls.has(src)) {
        return Promise.resolve(src);
    }

    return withTimeout((resolve, reject) => {
        const video = document.createElement('video');
        const handleReady = () => {
            cleanup();
            preloadedUrls.add(src);
            preloadedVideoElements.set(src, video);
            resolve();
        };
        const handleError = () => {
            cleanup();
            reject(new Error(`Failed to preload video: ${src}`));
        };
        const cleanup = () => {
            video.removeEventListener('loadeddata', handleReady);
            video.removeEventListener('canplay', handleReady);
            video.removeEventListener('canplaythrough', handleReady);
            video.removeEventListener('error', handleError);
        };

        video.preload = 'auto';
        video.muted = true;
        video.playsInline = true;
        video.addEventListener('loadeddata', handleReady, { once: true });
        video.addEventListener('canplay', handleReady, { once: true });
        video.addEventListener('canplaythrough', handleReady, { once: true });
        video.addEventListener('error', handleError, { once: true });
        video.src = src;
        video.load();
    }, timeoutMs, src);
};

export const preloadImage = (src, timeoutMs = IMAGE_TIMEOUT_MS) => {
    if (preloadedUrls.has(src)) {
        return Promise.resolve(src);
    }

    return withTimeout((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            preloadedUrls.add(src);
            resolve();
        };
        image.onerror = () => reject(new Error(`Failed to preload image: ${src}`));
        image.src = src;
    }, timeoutMs, src);
};

const logFailuresInDevelopment = (media, results) => {
    if (!import.meta.env?.DEV) {
        return;
    }

    results.forEach((result, index) => {
        if (result.status === 'rejected') {
            console.warn(`Agent media preload failed: ${media[index].url}`, result.reason);
        }
    });
};

const runPreloadBatch = async () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return [];
    }

    const media = getAllAgentMedia().filter(({ url }) => !preloadedUrls.has(url));
    const settledPromise = Promise.allSettled(
        media.map(({ type, url }) => (
            type === 'video' ? preloadVideo(url) : preloadImage(url)
        )),
    ).then((results) => {
        logFailuresInDevelopment(media, results);
        return results;
    });

    let globalTimeoutId;
    const globalTimeout = new Promise((resolve) => {
        globalTimeoutId = window.setTimeout(() => resolve('timeout'), GLOBAL_TIMEOUT_MS);
    });

    const result = await Promise.race([settledPromise, globalTimeout]);
    window.clearTimeout(globalTimeoutId);

    return result;
};

export const preloadAgentMedia = () => {
    if (!preloadPromise) {
        preloadPromise = runPreloadBatch();
    }

    return preloadPromise;
};

export const getPreloadedAgentMediaUrls = () => new Set(preloadedUrls);

export const getPreloadedAgentVideoCount = () => preloadedVideoElements.size;
