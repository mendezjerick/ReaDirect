const configuredBaseUrl = import.meta.env?.VITE_REA_AGENT_ASSET_BASE_URL || '/ia-assets';
const mediaRevision = 'phase5-20260607-4';

export const agentAssetBaseUrl = configuredBaseUrl.replace(/\/+$/, '');

export const buildAgentMediaUrl = (relativePath) =>
    `${agentAssetBaseUrl}/${String(relativePath).replace(/^\/+/, '')}?v=${mediaRevision}`;

const video = (path) => Object.freeze({ type: 'video', path });
const image = (path) => Object.freeze({ type: 'image', path });

export const agentMedia = Object.freeze({
    Ciel: Object.freeze({
        idle: video('videos/Ciel/c-idle.mp4'),
        chibi: image('images/Ciel/cchibi.png'),
        fallback: image('images/Ciel/Ciel.png'),
        actions: Object.freeze({
            thinking: Object.freeze([
                video('videos/Ciel/c-thinking-1.mp4'),
                video('videos/Ciel/c-thinking-2.mp4'),
                video('videos/Ciel/c-thinking-3.mp4'),
            ]),
            thinking_1: Object.freeze([video('videos/Ciel/c-thinking-1.mp4')]),
            thinking_2: Object.freeze([video('videos/Ciel/c-thinking-2.mp4')]),
            thinking_3: Object.freeze([video('videos/Ciel/c-thinking-3.mp4')]),
            talk: Object.freeze([video('videos/Ciel/c-talk.mp4')]),
            happy: Object.freeze([video('videos/Ciel/c-happy.mp4')]),
            confused: Object.freeze([video('videos/Ciel/c-confused.mp4')]),
            advise: Object.freeze([video('videos/Ciel/c-advise.mp4')]),
            clap: Object.freeze([video('videos/Ciel/c-clap.mp4')]),
            congrats: Object.freeze([video('videos/Ciel/c-congrats.mp4')]),
        }),
    }),
    Vivian: Object.freeze({
        idle: video('videos/Vivian/v-idle.mp4'),
        chibi: image('images/Vivian/vchibi.png'),
        fallback: image('images/Vivian/Vivian.png'),
        actions: Object.freeze({
            talk: Object.freeze([video('videos/Vivian/v-talk.mp4')]),
            thinking: Object.freeze([video('videos/Vivian/v-think.mp4')]),
            congrats: Object.freeze([video('videos/Vivian/v-congrats.mp4')]),
        }),
    }),
    Estelle: Object.freeze({
        idle: video('videos/Estelle/e-idle.mp4'),
        chibi: image('images/Estelle/echibi.png'),
        fallback: image('images/Estelle/Estelle.png'),
        actions: Object.freeze({
            talk: Object.freeze([video('videos/Estelle/e-talk.mp4')]),
            results: Object.freeze([
                video('videos/Estelle/e-results-1.mp4'),
                video('videos/Estelle/e-results-2.mp4'),
            ]),
            congrats: Object.freeze([video('videos/Estelle/e-congrats.mp4')]),
        }),
    }),
});

const agentAliases = Object.freeze({
    assessment: 'Vivian',
    vivian: 'Vivian',
    coach_feedback: 'Ciel',
    coachfeedback: 'Ciel',
    ciel: 'Ciel',
    evaluator: 'Estelle',
    evaluator_recommendation: 'Estelle',
    evaluatorrecommendation: 'Estelle',
    estelle: 'Estelle',
});

const normalizeKey = (value) => String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/^miss[\s_-]*/, '')
    .replace(/[\s-]+/g, '_');

const actionAliases = Object.freeze({
    Ciel: Object.freeze({
        c_idle: 'idle',
        c_talk: 'talk',
        talk: 'talk',
        talking: 'talk',
        speaking: 'talk',
        c_thinking_1: 'thinking_1',
        c_thinking_2: 'thinking_2',
        c_thinking_3: 'thinking_3',
        thinking_1: 'thinking_1',
        thinking_2: 'thinking_2',
        thinking_3: 'thinking_3',
        thinking: 'thinking',
        processing: 'thinking',
        c_happy: 'happy',
        happy: 'happy',
        correct: 'happy',
        c_confused: 'confused',
        confused: 'confused',
        incorrect: 'confused',
        c_advise: 'advise',
        advise: 'advise',
        encouraging: 'advise',
        correction: 'advise',
        c_clap: 'clap',
        clap: 'clap',
        praise: 'clap',
        c_congrats: 'congrats',
        congrats: 'congrats',
        celebrating: 'congrats',
    }),
    Vivian: Object.freeze({
        talk: 'talk',
        talking: 'talk',
        speaking: 'talk',
        thinking: 'thinking',
        processing: 'thinking',
        encouraging: 'thinking',
        retry: 'thinking',
        congrats: 'congrats',
        celebrating: 'congrats',
    }),
    Estelle: Object.freeze({
        talk: 'talk',
        talking: 'talk',
        speaking: 'talk',
        results: 'results',
        pointing: 'results',
        presenting: 'results',
        congrats: 'congrats',
        celebrating: 'congrats',
    }),
});

const withUrl = (media) => Object.freeze({
    ...media,
    url: buildAgentMediaUrl(media.path),
});

export const getAgentName = (agent) => agentAliases[normalizeKey(agent)] ?? 'Ciel';

export const getAgentAlt = (agent) => `Miss ${getAgentName(agent)}`;

export const getAgentFallbackMedia = (agent) =>
    withUrl(agentMedia[getAgentName(agent)].fallback);

export const getAgentChibiMedia = (agent) =>
    withUrl(agentMedia[getAgentName(agent)].chibi ?? agentMedia[getAgentName(agent)].fallback);

export const getAgentIdleMedia = (agent) => {
    const registry = agentMedia[getAgentName(agent)];
    return withUrl(registry.idle ?? registry.fallback);
};

export const getAgentActionName = (agent, action, allowCongrats = false) => {
    const agentName = getAgentName(agent);
    const actionName = actionAliases[agentName][normalizeKey(action)] ?? 'idle';

    if (actionName === 'congrats' && !allowCongrats) {
        return agentName === 'Estelle' ? 'results' : 'idle';
    }

    return actionName;
};

export const getAgentActionMedia = (
    agent,
    action,
    { allowCongrats = false, random = Math.random } = {},
) => {
    const agentName = getAgentName(agent);
    const actionName = getAgentActionName(agentName, action, allowCongrats);
    const variants = agentMedia[agentName].actions[actionName];

    if (!variants?.length) {
        return getAgentIdleMedia(agentName);
    }

    const index = Math.min(
        variants.length - 1,
        Math.max(0, Math.floor(random() * variants.length)),
    );

    return withUrl(variants[index]);
};

export const getAgentImage = (agent) => getAgentFallbackMedia(agent).url;

const appendMediaUrl = (urls, media) => {
    if (!media?.path) return;

    urls.add(buildAgentMediaUrl(media.path));
};

const orderedUnique = (urls) => [...new Set(urls.filter(Boolean))];

export const getAgentCoreMediaUrls = (agent) => {
    const registry = agentMedia[getAgentName(agent)];
    const urls = new Set();

    appendMediaUrl(urls, registry.chibi);
    appendMediaUrl(urls, registry.fallback);
    appendMediaUrl(urls, registry.idle);

    return [...urls];
};

export const getAgentActionMediaUrls = (agent, actions = null) => {
    const agentName = getAgentName(agent);
    const registry = agentMedia[agentName];
    const actionNames = actions?.length
        ? orderedUnique(actions.map((action) => getAgentActionName(agentName, action, true)))
        : Object.keys(registry.actions);
    const urls = new Set();

    actionNames.forEach((actionName) => {
        registry.actions[actionName]?.forEach((media) => appendMediaUrl(urls, media));
    });

    return [...urls];
};

export const getAgentMediaUrls = (
    agent,
    { actions = null, includeCore = true } = {},
) => orderedUnique([
    ...(includeCore ? getAgentCoreMediaUrls(agent) : []),
    ...getAgentActionMediaUrls(agent, actions),
]);

const stagedAgentMedia = Object.freeze({
    welcome: Object.freeze([
        Object.freeze({ agent: 'Vivian', coreOnly: true }),
        Object.freeze({ agent: 'Ciel', coreOnly: true }),
        Object.freeze({ agent: 'Estelle', coreOnly: true }),
    ]),
    assessment: Object.freeze([
        Object.freeze({ agent: 'Vivian', actions: Object.freeze(['talk', 'thinking', 'congrats']) }),
        Object.freeze({ agent: 'Ciel', coreOnly: true }),
        Object.freeze({ agent: 'Estelle', coreOnly: true }),
    ]),
    module: Object.freeze([
        Object.freeze({
            agent: 'Ciel',
            actions: Object.freeze([
                'talk',
                'thinking',
                'thinking_1',
                'thinking_2',
                'thinking_3',
                'happy',
                'confused',
                'advise',
                'clap',
                'congrats',
            ]),
        }),
        Object.freeze({ agent: 'Vivian', coreOnly: true }),
        Object.freeze({ agent: 'Estelle', coreOnly: true }),
    ]),
    evaluation: Object.freeze([
        Object.freeze({ agent: 'Estelle', actions: Object.freeze(['talk', 'results', 'congrats']) }),
        Object.freeze({ agent: 'Ciel', coreOnly: true }),
        Object.freeze({ agent: 'Vivian', coreOnly: true }),
    ]),
});

export const getAgentMediaUrlsForStage = (stage = 'welcome') => {
    if (stage === 'all') {
        return getAllAgentMediaUrls();
    }

    const plan = stagedAgentMedia[stage] ?? stagedAgentMedia.welcome;

    return orderedUnique(plan.flatMap((entry) => entry.coreOnly
        ? getAgentCoreMediaUrls(entry.agent)
        : getAgentMediaUrls(entry.agent, { actions: entry.actions })));
};

export const getAgentMediaStageForRoute = (href = '') => {
    const path = String(href ?? '').toLowerCase();

    if (
        path.includes('/summary')
        || path.includes('/result')
        || path.includes('/progress')
        || path.includes('/completion')
        || path.includes('/crla')
        || path.includes('/module-placement')
    ) {
        return 'evaluation';
    }

    if (
        path.includes('/final-assessment')
        || path.includes('/diagnostic')
        || path.includes('/learner/access')
        || path.includes('/story-selection')
        || path.includes('/task')
    ) {
        return 'assessment';
    }

    if (
        path.includes('/modules')
        || path.includes('/module')
        || path.includes('/reading')
    ) {
        return 'module';
    }

    return 'welcome';
};

export const getAgentMediaUrlsForRoute = (href = '') =>
    getAgentMediaUrlsForStage(getAgentMediaStageForRoute(href));

export const getAllAgentMediaUrls = () => orderedUnique(
    Object.keys(agentMedia).flatMap((agent) => getAgentMediaUrls(agent)),
);
