<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    user: { id: number; name: string; email: string };
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
});
const saved = ref(false);
const initials = computed(() => props.user.name.slice(0, 1).toUpperCase());

const submit = () => {
    saved.value = false;
    form.patch('/profile', {
        preserveScroll: true,
        onSuccess: () => { saved.value = true; },
    });
};
</script>

<template>
    <Head title="Perfil" />
    <AppLayout>
        <div class="page-content profile-page">
            <header class="page-heading">
                <div><h1>Personalizar conta</h1><p>Seu perfil nas mesas e entre os jogadores.</p></div>
            </header>
            <section class="profile-card">
                <aside class="profile-aside">
                    <span class="profile-avatar">{{ initials }}</span>
                    <strong>{{ user.name }}</strong>
                    <span>Jogador</span>
                </aside>
                <form class="profile-form" @submit.prevent="submit">
                    <h2>Dados do perfil</h2>
                    <p>Atualize o nome e o e-mail associados à sua conta.</p>
                    <label for="profile-name">Nome de jogador</label>
                    <input id="profile-name" v-model="form.name" maxlength="255" autocomplete="name" required />
                    <p v-if="form.errors.name" class="input-error">{{ form.errors.name }}</p>
                    <label for="profile-email">E-mail</label>
                    <input id="profile-email" v-model="form.email" type="email" autocomplete="email" required />
                    <p v-if="form.errors.email" class="input-error">{{ form.errors.email }}</p>
                    <button class="button-primary" type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Salvando…' : 'Salvar perfil' }}
                    </button>
                    <span v-if="saved" class="save-notice" role="status">Perfil salvo.</span>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
