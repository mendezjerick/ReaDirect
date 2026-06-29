export const vivianAsrReceivedCues = [
    {
        text: "I heard your answer. Let's keep going.",
        lineKey: 'vivian.asr.received_01',
    },
    {
        text: "Your answer came through. Let's continue to the next item.",
        lineKey: 'vivian.asr.received_02',
    },
    {
        text: "Thank you. I received your answer, so let's move forward.",
        lineKey: 'vivian.asr.received_03',
    },
];

export const vivianAsrReceivedCueForIndex = (index = 0) => {
    const normalized = Number.isFinite(Number(index)) ? Math.max(0, Number(index)) : 0;

    return vivianAsrReceivedCues[normalized % vivianAsrReceivedCues.length];
};

export const vivianAsrReceivedCueForItem = (item = null, items = []) => {
    const sequence = Number(item?.sequence ?? item?.position ?? item?.order);
    if (Number.isFinite(sequence) && sequence > 0) {
        return vivianAsrReceivedCueForIndex(sequence - 1);
    }

    const index = Array.isArray(items)
        ? items.findIndex((candidate) => candidate?.id === item?.id)
        : -1;

    return vivianAsrReceivedCueForIndex(index >= 0 ? index : 0);
};
