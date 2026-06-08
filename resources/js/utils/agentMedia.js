const configuredBaseUrl = import.meta.env?.VITE_REA_AGENT_ASSET_BASE_URL || '/';
const mediaRevision = 'phase5-20260607-4';

export const agentAssetBaseUrl = configuredBaseUrl.replace(/\/+$/, '');

export const buildAgentMediaUrl = (relativePath) =>
    `${agentAssetBaseUrl}/${String(relativePath).replace(/^\/+/, '')}?v=${mediaRevision}`;

const video = (path) => Object.freeze({ type: 'video', path });
const image = (path) => Object.freeze({ type: 'image', path });

export const agentMedia = Object.freeze({
    Ciel: Object.freeze({
        idle: video('videos/Ciel/c-idle.mp4'),
        fallback: image('images/Ciel/Ciel.png'),
        actions: Object.freeze({
            thinking: Object.freeze([
                video('videos/Ciel/c-thinking-1.mp4'),
                video('videos/Ciel/c-thinking-2.mp4'),
                video('videos/Ciel/c-thinking-3.mp4'),
            ]),
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
        fallback: image('images/Vivian/Vivian.png'),
        actions: Object.freeze({
            talk: Object.freeze([video('videos/Vivian/v-talk.mp4')]),
            thinking: Object.freeze([video('videos/Vivian/v-think.mp4')]),
            congrats: Object.freeze([video('videos/Vivian/v-congrats.mp4')]),
        }),
    }),
    Estelle: Object.freeze({
        idle: video('videos/Estelle/e-idle.mp4'),
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
        talk: 'talk',
        talking: 'talk',
        speaking: 'talk',
        thinking: 'thinking',
        processing: 'thinking',
        happy: 'happy',
        correct: 'happy',
        confused: 'confused',
        incorrect: 'confused',
        advise: 'advise',
        encouraging: 'advise',
        correction: 'advise',
        clap: 'clap',
        praise: 'clap',
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

export const getAllAgentMediaUrls = () => {
    const urls = new Set();

    Object.values(agentMedia).forEach((registry) => {
        [registry.idle, registry.fallback]
            .filter(Boolean)
            .forEach((media) => urls.add(buildAgentMediaUrl(media.path)));

        Object.values(registry.actions).forEach((variants) => {
            variants.forEach((media) => urls.add(buildAgentMediaUrl(media.path)));
        });
    });

    return [...urls];
};
