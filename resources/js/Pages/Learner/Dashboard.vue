<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import LessonCard from '../../Components/LessonCard.vue';
import ProgressPath from '../../Components/ProgressPath.vue';
import LessonNode from '../../Components/LessonNode.vue';
import StreakCard from '../../Components/StreakCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';

defineProps({
    learner: Object,
    modules: Array,
});
</script>

<template>
    <LearnerLayout :progress="20">
        <div class="grid gap-6 md:grid-cols-[1fr_280px]">
            <section>
                <h1 class="text-4xl font-black text-text">Hi {{ learner?.first_name ?? 'reader' }}!</h1>
                <p class="mt-2 text-xl font-bold text-muted">Let us follow your reading path.</p>
                <div class="mt-6">
                    <Link href="/learner/diagnostic"><PrimaryButton>Start diagnostic</PrimaryButton></Link>
                </div>
                <div class="mt-8 rounded-[32px] border border-border bg-surface p-6 shadow-xl shadow-primary/10">
                    <ProgressPath>
                        <LessonNode title="Diagnostic reading check" status="active" :number="1" />
                        <LessonNode v-for="module in modules" :key="module.key" :title="module.title" status="locked" :number="module.sequence + 1" />
                    </ProgressPath>
                </div>
            </section>
            <aside class="grid content-start gap-4">
                <StreakCard :days="1" />
                <ScoreCard label="Badges" value="0" />
                <LessonCard title="Next step" description="Complete the diagnostic reading check." active />
            </aside>
        </div>
    </LearnerLayout>
</template>
