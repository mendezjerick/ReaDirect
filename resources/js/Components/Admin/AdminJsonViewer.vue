<script setup>
import { computed } from 'vue';

const props = defineProps({ value: { type: [Object, Array, String, Number, Boolean, null], default: null } });

const format = (value) => {
    if (value === null || value === undefined) {
        return 'null';
    }
    return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
};

const highlighted = computed(() => {
    let json = format(props.value);
    
    // Don't syntax highlight raw strings that aren't JSON objects to avoid weird matching
    if (typeof props.value === 'string') {
        return json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'text-sky-300';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'text-slate-300 font-medium'; // Key
            } else {
                cls = 'text-emerald-300'; // String value
            }
        } else if (/true|false/.test(match)) {
            cls = 'text-orange-400 font-medium'; // Boolean
        } else if (/null/.test(match)) {
            cls = 'text-rose-400 font-bold'; // Null
        } else {
            cls = 'text-violet-300'; // Number
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
});
</script>

<template>
    <pre class="max-h-[600px] w-full overflow-auto bg-slate-950 p-5 text-[13px] leading-[1.6] text-slate-100 font-mono tracking-wide custom-scrollbar" v-html="highlighted"></pre>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #020617; 
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155; 
    border-radius: 5px;
    border: 2px solid #020617;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #475569; 
}
</style>
