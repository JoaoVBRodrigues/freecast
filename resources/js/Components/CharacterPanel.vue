<script setup lang="ts">
import axios from 'axios';
import { onMounted, ref, watch } from 'vue';

interface Character {
    id: number; name: string; hp_current: number; hp_max: number; revision: number; can_edit: boolean;
    portrait_url: string | null; notes?: string; attributes?: Array<{ name: string; value: string }>;
    resources?: Array<{ name: string; current: number; max: number }>;
    assigned_user_id?: number | null; portrait_asset_id?: number | null; is_public?: boolean;
}
interface Participant { id: string; character_id: number; name: string; initiative: number }
interface Combat { revision: number; active: boolean; round: number; active_id: string | null; participants: Participant[] }
const props = defineProps<{
    roomId: number; canManage: boolean; version: number;
    members?: Array<{ id: number; name: string; role?: string }>;
    assets?: Array<{ id: number; name: string }>;
    token?: { id: number; revision: number; character_id?: number | null } | null;
}>();
const emit = defineEmits<{ changed: [] }>();
const characters = ref<Character[]>([]);
const combat = ref<Combat>({ revision: 1, active: false, round: 0, active_id: null, participants: [] });
const draft = ref<Character | null>(null);
const error = ref('');
const busy = ref(false);
const combatCharacter = ref<number | null>(null);
const initiative = ref(0);
let generation = 0;
async function refresh() {
    const current = ++generation;
    try {
        const response = await axios.get(`/mesas/${props.roomId}/personagens`);
        if (current !== generation) return;
        characters.value = response.data.characters;
        combat.value = response.data.combat;
        // Reassignment revokes an open editor too; never keep an obsolete private draft.
        if (draft.value?.id && !characters.value.some(c => c.id === draft.value?.id && c.can_edit)) draft.value = null;
    } catch { error.value = 'Não foi possível atualizar fichas e combate.'; }
}
onMounted(refresh);
watch(() => props.version, refresh);
function open(character?: Character) {
    draft.value = character ? JSON.parse(JSON.stringify(character)) : {
        id: 0, name: '', hp_current: 0, hp_max: 0, revision: 1, can_edit: true,
        portrait_url: null, notes: '', attributes: [], resources: [], assigned_user_id: null, portrait_asset_id: null, is_public: false,
    };
    error.value = '';
}
async function send(path: string, data: Record<string, unknown>) {
    if (busy.value) return false;
    busy.value = true;
    error.value = '';
    try {
        await axios.post(`/mesas/${props.roomId}/${path}`, { ...data, idempotency_key: crypto.randomUUID() });
        await refresh();
        emit('changed');
        return true;
    } catch (failure) {
        error.value = axios.isAxiosError(failure) ? (failure.response?.data.message ?? 'Falha ao salvar.') : 'Falha ao salvar.';
        if (axios.isAxiosError(failure) && failure.response?.status === 409) {
            await refresh();
            // Keep edits for comparison; reopening loads the current revision.
            error.value = 'Conflito: reabra a ficha antes de salvar novamente.';
        }
        return false;
    } finally { busy.value = false; }
}
async function save() {
    const c = draft.value;
    if (!c) return;
    const fields: Record<string, unknown> = { action: c.id ? 'update' : 'create', character_id: c.id || undefined, expected_revision: c.revision,
        name: c.name, notes: c.notes, attributes: c.attributes, resources: c.resources, hp_current: c.hp_current, hp_max: c.hp_max };
    if (props.canManage) Object.assign(fields, { assigned_user_id: c.assigned_user_id, portrait_asset_id: c.portrait_asset_id, is_public: c.is_public });
    if (await send('personagens/comandos', fields)) draft.value = null;
}
async function remove(c: Character) {
    if (window.confirm(`Remover ${c.name}?`)) {
        if (await send('personagens/comandos', { action: 'delete', character_id: c.id, expected_revision: c.revision })) draft.value = null;
    }
}
function link(c: Character, unlink = false) {
    if (!props.token) return;
    return send('personagens/comandos', { action: 'link', character_id: c.id, expected_revision: c.revision, token_id: props.token.id, token_revision: props.token.revision, unlink });
}
function combatCommand(action: string, extra: Record<string, unknown> = {}) {
    return send('combate/comandos', { action, expected_revision: combat.value.revision, ...extra });
}
</script>

<template>
    <section class="character-panel" aria-label="Personagens e combate">
        <header><strong>Personagens</strong><button v-if="canManage" :disabled="busy" @click="open()">Novo personagem</button></header>
        <p v-if="error" role="alert">{{ error }}</p>
        <div class="character-list">
            <article v-for="c in characters" :key="c.id" :data-character-id="c.id">
                <img v-if="c.portrait_url" :src="c.portrait_url" :alt="`Retrato de ${c.name}`" />
                <button @click="open(c)">{{ c.name }}</button><span>PV {{ c.hp_current }}/{{ c.hp_max }}</span>
                <meter :value="c.hp_current" :max="c.hp_max || 1" :aria-label="`PV de ${c.name}`" />
                <button v-if="canManage && token" :disabled="busy" @click="link(c, token.character_id === c.id)">{{ token.character_id === c.id ? 'Desvincular token' : 'Vincular token' }}</button>
            </article>
            <small v-if="!characters.length">Nenhum personagem visível.</small>
        </div>
        <form v-if="draft" class="character-form" @submit.prevent="save">
            <strong>{{ draft.id ? 'Ficha' : 'Novo personagem' }}</strong>
            <label>Nome do personagem<input v-model="draft.name" :disabled="!draft.can_edit" required maxlength="100" /></label>
            <div class="character-pv"><label>PV atual<input v-model.number="draft.hp_current" :disabled="!draft.can_edit" type="number" min="0" :max="draft.hp_max" /></label><label>PV máximo<input v-model.number="draft.hp_max" :disabled="!draft.can_edit" type="number" min="0" max="1000000" /></label></div>
            <template v-if="draft.can_edit">
                <label>Anotações privadas<textarea v-model="draft.notes" maxlength="20000" /></label>
                <template v-if="canManage">
                    <label>Jogador responsável<select v-model="draft.assigned_user_id"><option :value="null">Sem atribuição</option><option v-for="m in members?.filter(m => !m.role || m.role === 'player')" :key="m.id" :value="m.id">{{ m.name }}</option></select></label>
                    <label>Retrato da biblioteca<select v-model="draft.portrait_asset_id"><option :value="null">Sem retrato</option><option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }}</option></select></label>
                    <label><input v-model="draft.is_public" type="checkbox" /> Nome e PV visíveis à mesa</label>
                </template>
                <fieldset><legend>Atributos</legend><div v-for="(a, i) in draft.attributes" :key="i" class="character-pv"><input v-model="a.name" aria-label="Nome do atributo" required maxlength="60" /><input v-model="a.value" aria-label="Valor do atributo" required maxlength="200" /><button type="button" @click="draft.attributes?.splice(i, 1)">×</button></div><button type="button" @click="draft.attributes?.push({ name: '', value: '' })">Adicionar atributo</button></fieldset>
                <fieldset><legend>Recursos</legend><div v-for="(r, i) in draft.resources" :key="i"><input v-model="r.name" aria-label="Nome do recurso" required maxlength="60" /><div class="character-pv"><input v-model.number="r.current" type="number" min="0" :max="r.max" aria-label="Recurso atual" /><input v-model.number="r.max" type="number" min="0" max="1000000" aria-label="Recurso máximo" /><button type="button" @click="draft.resources?.splice(i, 1)">×</button></div></div><button type="button" @click="draft.resources?.push({ name: '', current: 0, max: 0 })">Adicionar recurso</button></fieldset>
                <button :disabled="busy">Salvar personagem</button><button v-if="canManage && draft.id" type="button" :disabled="busy" @click="remove(draft)">Remover personagem</button>
            </template>
            <button type="button" @click="draft = null">Fechar ficha</button>
        </form>
        <hr />
        <header><strong>Combate · Rodada {{ combat.round }}</strong><span>{{ combat.active ? 'Em andamento' : 'Encerrado' }}</span></header>
        <ol class="combat-list"><li v-for="p in combat.participants" :key="p.id" :class="{ active: p.id === combat.active_id }"><strong v-if="p.id === combat.active_id">▶ </strong>{{ p.name }} · {{ p.initiative }}<template v-if="canManage"><input :value="p.initiative" type="number" :aria-label="`Iniciativa de ${p.name}`" @change="combatCommand('initiative', { participant_id: p.id, initiative: Number(($event.target as HTMLInputElement).value) })" /><button :disabled="busy" @click="combatCommand('remove', { participant_id: p.id })">Remover</button></template></li></ol>
        <div v-if="canManage" class="combat-actions">
            <select v-model="combatCharacter" aria-label="Personagem no combate"><option :value="null">Escolha personagem</option><option v-for="c in characters" :key="c.id" :value="c.id">{{ c.name }}</option></select>
            <input v-model.number="initiative" aria-label="Iniciativa" type="number" min="-1000000" max="1000000" />
            <button :disabled="busy || !combatCharacter" @click="combatCommand('add', { character_id: combatCharacter, initiative, ...(token?.character_id === combatCharacter ? { token_id: token.id } : {}) })">Adicionar ao combate</button>
            <button :disabled="busy" @click="combatCommand(combat.active ? 'end' : 'start')">{{ combat.active ? 'Encerrar combate' : 'Iniciar combate' }}</button>
            <button :disabled="busy || !combat.active || !combat.participants.length" @click="combatCommand('next')">Próximo turno</button>
        </div>
    </section>
</template>

<style scoped>
.character-panel { padding: 7px; font-size: 11px; overflow: auto; max-height: 70vh; color: var(--text-primary, #ddd); }
header, .character-pv { display: flex; align-items: center; gap: 5px; justify-content: space-between; }
.character-list article { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; padding: 5px 0; border-bottom: 1px solid #444; }
.character-list img { width: 24px; height: 24px; object-fit: cover; }
.character-list meter { width: 100%; height: 6px; }
.character-form { margin: 6px 0; padding: 6px; background: #282828; }
label { display: block; margin: 4px 0; }
input:not([type=checkbox]), textarea, select { display: block; width: 100%; min-width: 0; padding: 3px; font-size: 11px; background: #222; color: #ddd; border: 1px solid #555; }
.character-pv > * { min-width: 0; flex: 1; } button { padding: 3px 5px; border: 1px solid #555; background: #3b3b3b; margin: 2px; } button:disabled { opacity: .5; } fieldset { border: 1px solid #444; padding: 4px; margin: 4px 0; } .active { color: #f5bc62; } .combat-list input { display: inline-block; width: 48px; } .combat-actions { display: flex; flex-wrap: wrap; gap: 3px; } .combat-actions select { flex: 1; } .combat-actions input { width: 58px; } [role=alert] { color: #ffb8a3; }
</style>
