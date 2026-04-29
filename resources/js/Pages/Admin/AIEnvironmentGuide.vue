<script setup>
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { ArrowLeft } from 'lucide-vue-next';

const props = defineProps({
    guide: {
        type: String,
        default: '',
    },
});

const html = computed(() => {
    const lines = props.guide.split('\n');
    const output = [];
    let inCode = false;
    let codeLines = [];
    let listItems = [];

    const flushList = () => {
        if (!listItems.length) return;
        output.push(`<ul>${listItems.map((item) => `<li>${inline(item)}</li>`).join('')}</ul>`);
        listItems = [];
    };

    const flushCode = () => {
        if (!codeLines.length) return;
        output.push(`<pre><code>${escapeHtml(codeLines.join('\n'))}</code></pre>`);
        codeLines = [];
    };

    for (const line of lines) {
        if (line.startsWith('```')) {
            if (inCode) {
                flushCode();
                inCode = false;
            } else {
                flushList();
                inCode = true;
            }
            continue;
        }

        if (inCode) {
            codeLines.push(line);
            continue;
        }

        if (line.startsWith('# ')) {
            flushList();
            output.push(`<h1>${inline(line.slice(2))}</h1>`);
        } else if (line.startsWith('## ')) {
            flushList();
            output.push(`<h2>${inline(line.slice(3))}</h2>`);
        } else if (line.startsWith('### ')) {
            flushList();
            output.push(`<h3>${inline(line.slice(4))}</h3>`);
        } else if (line.startsWith('- ')) {
            listItems.push(line.slice(2));
        } else if (line.trim() === '') {
            flushList();
        } else {
            flushList();
            output.push(`<p>${inline(line)}</p>`);
        }
    }

    flushList();
    flushCode();

    return output.join('');
});

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function inline(value) {
    return escapeHtml(value).replace(/`([^`]+)`/g, '<code>$1</code>');
}
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-text">AI Environment Guide</h1>
                <p class="mt-1 text-sm font-medium text-muted">Laravel and FastAPI settings for real AI/ASR integration.</p>
            </div>
            <a
                href="/admin/dashboard"
                class="inline-flex items-center gap-2 rounded-lg border border-border/70 bg-surface px-3 py-2 text-xs font-extrabold text-text shadow-sm transition hover:bg-background"
            >
                <ArrowLeft :size="14" />
                Back to Dashboard
            </a>
        </div>

        <article class="env-guide rounded-lg border border-border/70 bg-surface px-6 py-6 shadow-sm" v-html="html" />
    </AdminLayout>
</template>

<style scoped>
.env-guide :deep(h1) {
    font-size: 1.5rem;
    font-weight: 900;
    margin-bottom: 1rem;
}

.env-guide :deep(h2) {
    border-top: 1px solid rgb(226 232 240);
    font-size: 1.05rem;
    font-weight: 900;
    margin-top: 1.35rem;
    padding-top: 1.1rem;
}

.env-guide :deep(h3) {
    font-size: 0.95rem;
    font-weight: 900;
    margin-top: 1rem;
}

.env-guide :deep(p) {
    color: rgb(71 85 105);
    font-size: 0.9rem;
    line-height: 1.65;
    margin-top: 0.65rem;
}

.env-guide :deep(ul) {
    list-style: disc;
    margin: 0.65rem 0 0 1.35rem;
}

.env-guide :deep(li) {
    color: rgb(71 85 105);
    font-size: 0.9rem;
    line-height: 1.55;
    margin-top: 0.25rem;
}

.env-guide :deep(pre) {
    background: rgb(15 23 42);
    border-radius: 0.5rem;
    color: rgb(226 232 240);
    font-size: 0.8rem;
    line-height: 1.5;
    margin-top: 0.75rem;
    overflow-x: auto;
    padding: 0.9rem;
}

.env-guide :deep(code) {
    border-radius: 0.3rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
}

.env-guide :deep(p code),
.env-guide :deep(li code) {
    background: rgb(241 245 249);
    color: rgb(15 23 42);
    font-size: 0.82rem;
    padding: 0.1rem 0.3rem;
}
</style>
