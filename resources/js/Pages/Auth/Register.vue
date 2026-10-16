<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Criar conta" />
    <GuestLayout>
        <h1>Vamos montar seu grupo.</h1>
        <p class="auth-intro">Crie seu perfil e prepare uma mesa com seus amigos.</p>
        <form class="auth-form" @submit.prevent="submit">
            <label for="name">Nome de jogador</label>
            <input id="name" v-model="form.name" type="text" autocomplete="name" placeholder="Como seus amigos chamam você?" required autofocus />
            <p v-if="form.errors.name" class="auth-error">{{ form.errors.name }}</p>

            <label for="email">E-mail</label>
            <input id="email" v-model="form.email" type="email" autocomplete="email" placeholder="voce@exemplo.com" required />
            <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>

            <label for="password">Senha</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" placeholder="Pelo menos 8 caracteres" required />
            <p v-if="form.errors.password" class="auth-error">{{ form.errors.password }}</p>

            <label for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" placeholder="Digite a senha novamente" required />
            <p v-if="form.errors.password_confirmation" class="auth-error">{{ form.errors.password_confirmation }}</p>

            <button class="button-primary auth-submit" type="submit" :disabled="form.processing">
                {{ form.processing ? 'Criando conta…' : 'Criar conta' }}
            </button>
        </form>
        <p class="auth-note">Já tem conta? <Link href="/login">Entrar</Link></p>
    </GuestLayout>
</template>
