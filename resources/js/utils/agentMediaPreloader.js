import {
    getAgentCoreMediaUrls,
    getAgentMediaUrlsForRoute,
    getAgentMediaUrlsForStage,
    getAllAgentMediaUrls,
} from './agentMedia';

const preloadedUrls = new Set();
const inFlightPreloads = new Map();
const stagePreloadPromises = new Map();
const completedStages = new Set();
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

const isImageUrl = (url) => /\.(png|jpe?g|webp|gif|svg)(?:[?#]|$)/i.test(url);

export const preloadImage = (src, timeoutMs = 10000) => {
    if (preloadedUrls.has(src)) return Promise.resolve(true);
    if (inFlightPreloads.has(src)) return inFlightPreloads.get(src);

    const promise = waitWithTimeout((finish) => {
        const image = new Image();
        image.onload = () => {
            preloadedUrls.add(src);
            finish(true);
        };
        image.onerror = () => finish(false);
        image.src = src;
    }, timeoutMs).finally(() => {
        inFlightPreloads.delete(src);
    });

    inFlightPreloads.set(src, promise);

    return promise;
};

export const preloadVideo = (src, timeoutMs = 10000) => {
    if (preloadedUrls.has(src)) return Promise.resolve(true);
    if (inFlightPreloads.has(src)) return inFlightPreloads.get(src);

    const promise = waitWithTimeout((finish) => {
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
    }, timeoutMs).finally(() => {
        inFlightPreloads.delete(src);
    });

    inFlightPreloads.set(src, promise);

    return promise;
};

const uniqueUrls = (urls) => [...new Set(urls.filter(Boolean))];

const preloadUrl = (url, options = {}) => (
    isImageUrl(url)
        ? preloadImage(url, options.timeoutMs)
        : preloadVideo(url, options.timeoutMs)
);

export const preloadAgentMediaUrls = async (urls, options = {}) => {
    const batchSize = Math.max(1, Number(options.batchSize ?? 3));
    const orderedUrls = uniqueUrls(urls);
    const results = [];

    for (let index = 0; index < orderedUrls.length; index += batchSize) {
        const batch = orderedUrls.slice(index, index + batchSize);
        const settled = await Promise.allSettled(batch.map(async (url) => {
            const loaded = await preloadUrl(url, options);

            if (!loaded && import.meta.env.DEV) {
                console.warn(`[ReaDirect agents] Could not preload ${url}`);
            }

            return { url, loaded };
        }));

        results.push(...settled);
    }

    return results;
};

const runStagePreload = (stage, urls, options = {}) => {
    if (completedStages.has(stage)) return Promise.resolve([]);
    if (stagePreloadPromises.has(stage)) return stagePreloadPromises.get(stage);

    const work = preloadAgentMediaUrls(urls, options).finally(() => {
        completedStages.add(stage);
        stagePreloadPromises.delete(stage);

        if (stage === 'all') {
            preloadCompleted = true;
        }
    });

    stagePreloadPromises.set(stage, work);

    return work;
};

export const preloadAgentMedia = (stage = 'all', options = {}) => {
    const stageName = typeof stage === 'string' ? stage : 'all';
    const preloadOptions = typeof stage === 'string' ? options : stage;
    const urls = stageName === 'all'
        ? getAllAgentMediaUrls()
        : getAgentMediaUrlsForStage(stageName);

    return runStagePreload(stageName, urls, preloadOptions);
};

export const preloadAgentMediaForRoute = (href, options = {}) =>
    runStagePreload(`route:${href}`, getAgentMediaUrlsForRoute(href), options);

export const preloadAgentCoreMedia = (agent, options = {}) =>
    runStagePreload(`core:${agent}`, getAgentCoreMediaUrls(agent), options);

export const scheduleAgentMediaPreload = (stage = 'welcome', options = {}) => {
    if (typeof window === 'undefined') return null;

    const {
        delayMs = 900,
        idleTimeoutMs = 2500,
        ...preloadOptions
    } = options;
    const run = () => {
        preloadAgentMedia(stage, preloadOptions).catch(() => {});
    };

    if ('requestIdleCallback' in window) {
        return window.requestIdleCallback(run, { timeout: idleTimeoutMs });
    }

    return window.setTimeout(run, delayMs);
};

export const isAgentMediaPreloaded = () => preloadCompleted;
