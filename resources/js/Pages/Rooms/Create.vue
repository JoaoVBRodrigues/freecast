<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LockKeyhole, ShieldCheck } from '@lucide/vue';

const form = useForm({
    title: '',
    system: 'Sistema Próprio',
    description: '',
    is_public: true,
    password: '',
});

const submit = () => form.post('/mesas');
</script>

<template>
    <Head title="Criar mesa" />
    <AppLayout>
        <div class="page-content create-page">
            <Link class="back-link" href="/mesas"><ArrowLeft :size="15" /> Voltar às mesas</Link>
            <section class="form-panel">
                <div class="form-panel-heading">
                    <span class="form-heading-icon"><ShieldCheck :size="22" /></span>
                    <div><h1>Preparar uma mesa</h1><p>Escolha o cenário. Seus amigos podem entrar em seguida.</p></div>
                </div>

                <form class="room-form" @submit.prevent="submit">
                    <label class="field-label" for="room-title">Nome da mesa</label>
                    <input id="room-title" v-model="form.title" maxlength="80" placeholder="Ex.: A cidade sob a névoa" required />
                    <InputError :message="form.errors.title" />

                    <label class="field-label" for="room-system">Sistema</label>
                    <input id="room-system" v-model="form.system" maxlength="60" placeholder="Ex.: D&D 5ª Edição" required />
                    <InputError :message="form.errors.system" />

                    <label class="field-label" for="room-description">Descrição <span>opcional</span></label>
                    <textarea id="room-description" v-model="form.description" maxlength="600" rows="3" placeholder="Conte um pouco sobre a aventura."></textarea>
                    <InputError :message="form.errors.description" />

                    <fieldset class="visibility-options">
                        <legend>Quem pode encontrar esta mesa?</legend>
                        <label class="visibility-option" :class="{ 'is-selected': form.is_public }">
                            <input v-model="form.is_public" :value="true" type="radio" name="visibility" />
                            <span class="visibility-icon"><ShieldCheck :size="17" /></span>
                            <span><strong>Mesa aberta</strong><small>Aparece na lista de mesas online.</small></span>
                        </label>
                        <label class="visibility-option" :class="{ 'is-selected': !form.is_public }">
                            <input v-model="form.is_public" :value="false" type="radio" name="visibility" />
                            <span class="visibility-icon"><LockKeyhole :size="17" /></span>
                            <span><strong>Com senha</strong><small>Compartilhe a senha apenas com o grupo.</small></span>
                        </label>
                    </fieldset>
                    <div v-if="!form.is_public" class="password-field">
                        <label class="field-label" for="room-password">Senha da mesa</label>
                        <input id="room-password" v-model="form.password" type="password" minlength="6" autocomplete="new-password" required />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="form-actions">
                        <Link class="button-secondary" href="/mesas">Cancelar</Link>
                        <button class="button-primary" :disabled="form.processing" type="submit">
                            {{ form.processing ? 'Criando…' : 'Criar mesa' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
