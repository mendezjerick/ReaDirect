const normalizeKey = (value) => String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/^miss[\s_-]*/, '')
    .replace(/[\s-]+/g, '_');

const explicitAgents = Object.freeze({
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

const contextAgents = Object.freeze({
    assessment: 'Vivian',
    diagnostic: 'Vivian',
    final_assessment: 'Vivian',
    module: 'Ciel',
    practice: 'Ciel',
    mastery: 'Ciel',
    results: 'Estelle',
    routing: 'Estelle',
    summary: 'Estelle',
    recommendation: 'Estelle',
    placement: 'Estelle',
});

const idleLabels = new Set([
    '', 'idle', 'listening', 'neutral', 'none', 'ready',
    'recording', 'recorded', 'cleared', 'next', 'navigation',
]);
const resultContexts = new Set([
    'results', 'routing', 'summary', 'recommendation', 'placement',
]);

const actionMaps = Object.freeze({
    Ciel: Object.freeze({
        talk: 'talk',
        talking: 'talk',
        speaking: 'talk',
        thinking: 'thinking',
        processing: 'thinking',
        uploading: 'thinking',
        checking: 'thinking',
        evaluating: 'thinking',
        happy: 'happy',
        correct: 'happy',
        success: 'happy',
        good: 'happy',
        passed: 'happy',
        confused: 'confused',
        incorrect: 'confused',
        unclear: 'confused',
        low_confidence: 'confused',
        mismatch: 'confused',
        error: 'confused',
        failed: 'confused',
        invalid: 'confused',
        validation_failed: 'confused',
        upload_error: 'confused',
        asr_error: 'confused',
        advise: 'advise',
        encouraging: 'advise',
        retry: 'advise',
        correction: 'advise',
        hint: 'advise',
        missing_answer: 'advise',
        guidance: 'advise',
        clap: 'clap',
        praise: 'clap',
        strong_answer: 'clap',
        streak: 'clap',
        section_complete: 'clap',
        congrats: 'congrats',
        celebrating: 'congrats',
    }),
    Vivian: Object.freeze({
        thinking: 'thinking',
        processing: 'thinking',
        uploading: 'thinking',
        checking: 'thinking',
        encouraging: 'thinking',
        retry: 'thinking',
        unclear: 'thinking',
        low_confidence: 'thinking',
        error: 'thinking',
        failed: 'thinking',
        congrats: 'congrats',
        celebrating: 'congrats',
    }),
    Estelle: Object.freeze({
        results: 'results',
        pointing: 'results',
        presenting: 'results',
        summary: 'results',
        placement: 'results',
        recommendation: 'results',
        encouraging: 'results',
        congrats: 'congrats',
        celebrating: 'congrats',
    }),
});

const inferContextFromRoute = (route = '') => {
    const path = normalizeKey(route.replace(/[?#].*$/, '').replace(/^\/+/, ''));

    if (!path) return '';
    if (path.includes('final_assessment') && path.includes('summary')) return 'results';
    if (/(routing|summary|result|placement|recommendation)/.test(path)) return 'results';
    if (path.includes('final_assessment')) return 'final_assessment';
    if (path.includes('diagnostic')) return 'diagnostic';
    if (/(module|practice|mastery)/.test(path)) return 'module';

    return '';
};

export const getAgentForContext = ({ agent, agentType, context, route } = {}) => {
    const explicitKey = normalizeKey(agentType) || normalizeKey(agent);
    const explicit = explicitAgents[explicitKey];
    if (explicit) return explicit;

    const normalizedContext = normalizeKey(context) || inferContextFromRoute(route);
    return contextAgents[normalizedContext] ?? 'Ciel';
};

export const getActionForInteraction = ({
    agent,
    action,
    context,
    congratsAllowed = false,
} = {}) => {
    const agentName = explicitAgents[normalizeKey(agent)] ?? agent ?? 'Ciel';
    const actionName = normalizeKey(action);

    if (idleLabels.has(actionName)) return 'idle';

    const mappedAction = actionMaps[agentName]?.[actionName] ?? 'idle';

    if (mappedAction === 'congrats' && !congratsAllowed) {
        return agentName === 'Estelle' && resultContexts.has(normalizeKey(context))
            ? 'results'
            : 'idle';
    }

    return mappedAction;
};

export const resolveAgentInteraction = ({
    agent,
    agentType,
    action = 'idle',
    context,
    route,
    congratsAllowed = false,
} = {}) => {
    const resolvedContext = normalizeKey(context)
        || inferContextFromRoute(route)
        || ({
            Vivian: 'assessment',
            Ciel: 'module',
            Estelle: 'results',
        }[getAgentForContext({ agent, agentType, context, route })]);
    const resolvedAgent = getAgentForContext({ agent, agentType, context: resolvedContext, route });
    const resolvedAction = getActionForInteraction({
        agent: resolvedAgent,
        action,
        context: resolvedContext,
        congratsAllowed,
    });

    return Object.freeze({
        agent: resolvedAgent,
        action: resolvedAction,
        context: resolvedContext,
        congratsAllowed: congratsAllowed === true,
        shouldInteract: resolvedAction !== 'idle',
    });
};

export const normalizeAgentCue = (cue = {}, defaults = {}) =>
    resolveAgentInteraction({
        ...defaults,
        ...cue,
        congratsAllowed: cue.congratsAllowed
            ?? cue.allowCongrats
            ?? defaults.congratsAllowed
            ?? defaults.allowCongrats
            ?? false,
    });
