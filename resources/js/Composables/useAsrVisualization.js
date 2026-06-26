import { readonly, ref } from 'vue';

const enabled = ref(true); // Enabled by default so the user sees the feature!
const toastMessage = ref('');
const toastVisible = ref(false);
let toastTimer = null;

const showToast = (message) => {
    toastMessage.value = message;
    toastVisible.value = true;

    if (toastTimer) {
        window.clearTimeout(toastTimer);
    }

    toastTimer = window.setTimeout(() => {
        toastVisible.value = false;
        toastTimer = null;
    }, 1500);
};

const setEnabled = (value, announce = false) => {
    enabled.value = value === true;

    if (announce) {
        showToast(enabled.value ? 'Activated' : 'Deactivated');
    }
};

const toggle = () => {
    setEnabled(!enabled.value, true);
};

export const useAsrVisualization = () => ({
    enabled: readonly(enabled),
    toastMessage: readonly(toastMessage),
    toastVisible: readonly(toastVisible),
    setEnabled,
    toggle,
});
