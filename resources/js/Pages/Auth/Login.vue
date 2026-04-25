<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => form.post('/login');
</script>

<template>
    <AuthLayout>
        <form class="mt-8 grid gap-4" @submit.prevent="submit">
            <div>
                <label class="text-sm font-black text-muted" for="email">Email</label>
                <input id="email" v-model="form.email" class="mt-2 w-full rounded-2xl border border-border px-4 py-3 text-base font-semibold focus:border-primary focus:outline-none" type="email">
                <p v-if="form.errors.email" class="mt-2 text-sm font-bold text-danger">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="text-sm font-black text-muted" for="password">Password</label>
                <input id="password" v-model="form.password" class="mt-2 w-full rounded-2xl border border-border px-4 py-3 text-base font-semibold focus:border-primary focus:outline-none" type="password">
            </div>
            <PrimaryButton type="submit" :disabled="form.processing">Sign in</PrimaryButton>
        </form>
    </AuthLayout>
</template>
