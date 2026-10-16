<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => form.post('/login', {
    onFinish: () => form.reset('password'),
});
</script>

<template>
    <Head title="Entrar" />
    <GuestLayout>
        <h1>Que bom ter você de volta.</h1>
        <p class="auth-intro">Entre na sua conta para continuar a aventura.</p>
        <p v-if="status" class="auth-status">{{ status }}</p>
        <form class="auth-form" @submit.prevent="submit">
            <label for="email">E-mail</label>
            <input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="voce@exemplo.com" required autofocus />
            <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>

            <label for="password">Senha</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password" placeholder="Sua senha" required />
            <p v-if="form.errors.password" class="auth-error">{{ form.errors.password }}</p>

            <label class="remember-option">
                <input v-model="form.remember" type="checkbox" />
                Manter conectado
            </label>
            <button class="button-primary auth-submit" type="submit" :disabled="form.processing">
                {{ form.processing ? 'Entrando…' : 'Entrar' }}
            </button>
        </form>
        <p class="auth-note">Ainda não tem conta? <Link href="/register">Criar conta</Link></p>
    </GuestLayout>
</template>
