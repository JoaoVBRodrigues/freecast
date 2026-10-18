<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BookOpen,
    Check,
    ChevronDown,
    Dices,
    Ellipsis,
    LockKeyhole,
    MapPinned,
    MessageSquare,
    Music,
    Paperclip,
    PanelRight,
    Search,
    Send,
    Settings,
    Shield,
    Swords,
    Users,
    X,
    Trash2,
} from '@lucide/vue';
import { nextTick, onUnmounted, reactive, ref } from 'vue';
import { echo, useEcho, useEchoPresence } from '@laravel/echo-vue';

interface RoomMember {
    id: number;
    name: string;
    role: 'owner' | 'master' | 'player';
}

interface RoomMessage {
    id: number;
    command_id?: string;
    author_id?: number;
    author: string;
    recipient?: string | null;
    message_type?: 'public' | 'narration' | 'private' | 'roll';
    body: string;
    assets?: Array<{ id: number; name: string; url: string }>;
    created_at: string;
    expression?: string;
    components?: Array<{ kind: string; sign: number; count?: number; sides?: number; results?: number[]; value?: number; subtotal: number }>;
    total?: number;
    pending?: boolean;
}

interface Macro {
    id: number;
    scope: 'personal_room' | 'personal_global' | 'shared_room';
    name: string;
    body: string;
    revision: number;
}

interface RoomSyncEnvelope {
    eventId: string;
    stream: 'room' | 'masters' | 'user';
    sequence: number;
    type: string;
    commandId: string;
    payload: Record<string, unknown>;
}

interface PresenceUser {
    id: number;
    name: string;
    role: RoomMember['role'];
}

const props = defineProps<{
    room: { id: number; title: string; system: string; description: string; owner_name: string; is_owner: boolean; active_map_id: number | null; active_map_title: string | null };
    user_id: number;
    can_manage: boolean;
    members: RoomMember[];
    messages: RoomMessage[];
    macros: Macro[];
    library_assets: Array<{ id: number; name: string; url: string }>;
    panels: Record<string, boolean>;
}>();

const panelState = reactive({ ...props.panels });
const showPanelMenu = ref(false);
const search = ref('');
const messageList = ref<RoomMessage[]>([...props.messages]);
const macroList = ref<Macro[]>([...props.macros]);
const messageBody = ref('');
const messageType = ref<'public' | 'narration' | 'private'>('public');
const messageEditor = ref<HTMLTextAreaElement | null>(null);
const recipientId = ref<number | null>(null);
const selectedAssetIds = ref<number[]>([]);
const messageError = ref('');
const historyBefore = ref<number | null>(props.messages[0]?.id ?? null);
const historyLoading = ref(false);
const onlineMembers = ref<number[]>([]);
const macroName = ref('');
const macroBody = ref('');
const macroScope = ref<'personal_room' | 'personal_global' | 'shared_room'>('personal_room');
const macroError = ref('');
const eventCursors = reactive({ room: 0, masters: 0, user: 0 });
const receivedEvents = new Set<string>();
const panels = [
    { key: 'library', label: 'Exibir Biblioteca da mesa' },
    { key: 'macros', label: 'Exibir Macros' },
    { key: 'combat', label: 'Exibir Tracker de Combate' },
    { key: 'participants', label: 'Exibir participantes' },
];

function upsertMessage(item: RoomMessage): void {
    const existingIndex = messageList.value.findIndex((message) =>
        message.id === item.id || (item.command_id && message.command_id === item.command_id));
    if (existingIndex >= 0) {
        messageList.value.splice(existingIndex, 1, { ...messageList.value[existingIndex], ...item, pending: false });
    } else {
        messageList.value.push(item);
    }
    messageList.value.sort((left, right) => left.created_at.localeCompare(right.created_at));
    nextTick(() => {
        const list = document.querySelector('.chat-history');
        if (list) list.scrollTop = list.scrollHeight;
    });
}

function formatMessageBody(body: string): string {
    return body
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;')
        .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
        .replace(/__([^_\n]+)__/g, '<u>$1</u>')
        .replace(/\n/g, '<br>');
}

function wrapMessageSelection(marker: string): void {
    const editor = messageEditor.value;
    if (!editor) return;

    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const selected = messageBody.value.slice(start, end);
    messageBody.value = `${messageBody.value.slice(0, start)}${marker}${selected}${marker}${messageBody.value.slice(end)}`;
    nextTick(() => {
        editor.focus();
        const selectionStart = start + marker.length;
        editor.setSelectionRange(selectionStart, selectionStart + selected.length);
    });
}

function applyEvent(event: RoomSyncEnvelope): void {
    eventCursors[event.stream] = Math.max(eventCursors[event.stream], event.sequence);
    if (receivedEvents.has(event.eventId)) return;
    receivedEvents.add(event.eventId);
    const payload = event.payload;

    if (event.type === 'message.created') {
        upsertMessage({ ...(payload as unknown as RoomMessage), command_id: event.commandId });
    } else if (event.type === 'dice.rolled') {
        upsertMessage({
            ...(payload as unknown as RoomMessage),
            message_type: 'roll',
            command_id: event.commandId,
            body: '',
        });
    } else if (event.type === 'room.active_map') {
        router.reload({ only: ['room'] });
    } else if (event.type === 'macro.updated') {
        const macro = payload as unknown as Macro;
        if (macro.scope === 'shared_room' || macroList.value.some((item) => item.id === macro.id)) {
            const index = macroList.value.findIndex((item) => item.id === macro.id);
            if (index < 0) macroList.value.push(macro);
            else macroList.value.splice(index, 1, macro);
        }
    } else if (event.type === 'macro.deleted') {
        macroList.value = macroList.value.filter((macro) => macro.id !== payload.id);
    }
}

async function recoverEvents(): Promise<void> {
    try {
        let hasMore = true;
        while (hasMore) {
            const response = await axios.get(`/mesas/${props.room.id}/eventos`, {
                params: { after: { ...eventCursors } },
                headers: { Accept: 'application/json' },
            });
            const result = response.data as { events: RoomSyncEnvelope[]; cursor: typeof eventCursors; hasMore: boolean };
            result.events.forEach(applyEvent);
            eventCursors.room = Math.max(eventCursors.room, result.cursor.room);
            eventCursors.masters = Math.max(eventCursors.masters, result.cursor.masters);
            eventCursors.user = Math.max(eventCursors.user, result.cursor.user);
            hasMore = result.hasMore;
        }
    } catch (error) {
        console.error('Não foi possível recuperar os eventos da mesa.', error);
    }
}

const presence = useEchoPresence<RoomSyncEnvelope>(
    `rooms.${props.room.id}`,
    '.room.state.changed',
    applyEvent,
);
presence.channel()
    .here((users: PresenceUser[]) => {
        onlineMembers.value = users.map((user) => user.id);
        void recoverEvents();
    })
    .joining((user: PresenceUser) => {
        if (!onlineMembers.value.includes(user.id)) onlineMembers.value.push(user.id);
    })
    .leaving((user: PresenceUser) => {
        onlineMembers.value = onlineMembers.value.filter((id) => id !== user.id);
    });

useEcho<RoomSyncEnvelope>(`users.${props.user_id}`, '.room.state.changed', applyEvent, [], 'private');

const stopObservingConnection = echo().connector.onConnectionChange((status) => {
    if (status === 'connected') void recoverEvents();
});
onUnmounted(stopObservingConnection);

const togglePanel = (key: string) => {
    panelState[key] = !panelState[key];
    router.patch(`/mesas/${props.room.id}/preferencias`, { panels: { ...panelState } }, {
        preserveScroll: true,
        preserveState: true,
    });
};

async function sendMessage(): Promise<void> {
    const body = messageBody.value.trim();
    if (!body) return;
    const idempotencyKey = crypto.randomUUID();
    const optimistic: RoomMessage | null = body.startsWith('/r')
        ? null
        : {
            id: -Date.now(),
            command_id: idempotencyKey,
            author: props.members.find((member) => member.id === props.user_id)?.name ?? 'Você',
            author_id: props.user_id,
            recipient: messageType.value === 'private' ? props.members.find((member) => member.id === recipientId.value)?.name ?? null : null,
            message_type: messageType.value,
            body,
            assets: props.library_assets.filter((asset) => selectedAssetIds.value.includes(asset.id)),
            created_at: new Date().toISOString(),
            pending: true,
        };
    if (optimistic) upsertMessage(optimistic);
    messageError.value = '';
    try {
        const response = await axios.post(`/mesas/${props.room.id}/mensagens`, {
            body,
            message_type: messageType.value,
            recipient_user_id: messageType.value === 'private' ? recipientId.value : null,
            asset_ids: selectedAssetIds.value,
            idempotency_key: idempotencyKey,
        }, { headers: { Accept: 'application/json' } });
        if (optimistic) upsertMessage({ ...optimistic, id: response.data.messageId, pending: false });
        messageBody.value = '';
        selectedAssetIds.value = [];
    } catch (error) {
        if (optimistic) messageList.value = messageList.value.filter((message) => message.command_id !== idempotencyKey);
        messageError.value = 'Não foi possível enviar. Verifique o destinatário, os anexos e a conexão.';
        console.error('Falha ao enviar mensagem.', error);
    }
}

async function loadOlderMessages(): Promise<void> {
    if (!historyBefore.value || historyLoading.value) return;
    historyLoading.value = true;
    try {
        const response = await axios.get(`/mesas/${props.room.id}/historico`, {
            params: { before: historyBefore.value, limit: 50 },
            headers: { Accept: 'application/json' },
        });
        const older = response.data.messages as RoomMessage[];
        const list = document.querySelector('.chat-history');
        const oldHeight = list?.scrollHeight ?? 0;
        messageList.value = [...older.filter((item) => !messageList.value.some((current) => current.id === item.id)), ...messageList.value];
        historyBefore.value = response.data.nextBefore;
        await nextTick();
        if (list) list.scrollTop = list.scrollHeight - oldHeight;
    } catch (error) {
        messageError.value = 'Não foi possível carregar o histórico anterior.';
        console.error('Falha ao carregar histórico.', error);
    } finally {
        historyLoading.value = false;
    }
}

async function createMacro(): Promise<void> {
    macroError.value = '';
    try {
        await axios.post(`/mesas/${props.room.id}/macros`, {
            scope: macroScope.value,
            name: macroName.value,
            body: macroBody.value,
            idempotency_key: crypto.randomUUID(),
        }, { headers: { Accept: 'application/json' } });
        macroName.value = '';
        macroBody.value = '';
    } catch (error) {
        macroError.value = 'Não foi possível salvar a macro. Confira nome, escopo e permissões.';
        console.error('Falha ao criar macro.', error);
    }
}

async function runMacro(macro: Macro): Promise<void> {
    try {
        await axios.post(`/mesas/${props.room.id}/macros/${macro.id}/executar`, {
            idempotency_key: crypto.randomUUID(),
        }, { headers: { Accept: 'application/json' } });
    } catch (error) {
        messageError.value = `Não foi possível executar a macro “${macro.name}”.`;
        console.error('Falha ao executar macro.', error);
    }
}

async function editMacro(macro: Macro): Promise<void> {
    const name = window.prompt('Nome da macro', macro.name);
    if (name === null) return;
    const body = window.prompt('Ações (uma mensagem ou /r por linha)', macro.body);
    if (body === null) return;
    try {
        await axios.patch(`/mesas/${props.room.id}/macros/${macro.id}`, {
            name,
            body,
            expected_revision: macro.revision,
            idempotency_key: crypto.randomUUID(),
        }, { headers: { Accept: 'application/json' } });
    } catch (error) {
        macroError.value = 'A macro mudou ou você não tem permissão para editá-la.';
        console.error('Falha ao editar macro.', error);
    }
}

async function deleteMacro(macro: Macro): Promise<void> {
    if (!window.confirm(`Excluir a macro “${macro.name}”?`)) return;
    try {
        await axios.delete(`/mesas/${props.room.id}/macros/${macro.id}`, {
            data: { expected_revision: macro.revision, idempotency_key: crypto.randomUUID() },
            headers: { Accept: 'application/json' },
        });
    } catch (error) {
        macroError.value = 'Não foi possível excluir a macro.';
        console.error('Falha ao excluir macro.', error);
    }
}

const leaveRoom = () => router.post(`/mesas/${props.room.id}/sair`);
const copyInviteLink = () => navigator.clipboard.writeText(window.location.href);
const authorClass = (author: string) => author === props.room.owner_name ? 'message-master' : 'message-player';
const timestamp = (value: string) => new Date(value).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head :title="room.title" />
    <AppLayout :room-title="room.title" :room-id="room.id">
        <template #tab-actions>
            <div class="panel-menu-wrap">
                <button class="icon-button room-menu-button" type="button" aria-label="Exibir painéis" @click="showPanelMenu = !showPanelMenu">
                    <PanelRight :size="15" /><span>Painéis</span><ChevronDown :size="12" />
                </button>
                <div v-if="showPanelMenu" class="panel-menu" role="menu">
                    <label v-for="panel in panels" :key="panel.key">
                        <input :checked="panelState[panel.key]" type="checkbox" @change="togglePanel(panel.key)" />
                        {{ panel.label }}
                    </label>
                    <span class="panel-menu-divider"></span>
                    <button type="button" @click="showPanelMenu = false"><Check :size="13" /> Concluído</button>
                </div>
            </div>
            <button v-if="!room.is_owner" class="icon-button leave-room-button" type="button" @click="leaveRoom">
                <span>Sair da mesa</span><X :size="14" />
            </button>
            <Link class="icon-button map-library-tab-link" :href="`/mesas/${room.id}/mapas`" aria-label="Mapas e biblioteca" title="Mapas e biblioteca">
                <MapPinned :size="14" /><span>Mapas</span>
            </Link>
            <Link v-if="room.active_map_id" class="icon-button map-library-tab-link" :href="`/mesas/${room.id}/mapas/${room.active_map_id}`">
                <MapPinned :size="14" /><span>Mapa ativo: {{ room.active_map_title }}</span>
            </Link>
        </template>
        <section class="room-page">
            <div
                class="room-workspace"
                :class="{
                    'room-no-left': !panelState.library && !panelState.macros && !panelState.combat,
                    'room-no-right': !panelState.participants,
                    'room-with-right': panelState.participants,
                }"
            >
                <aside v-if="panelState.library || panelState.macros || panelState.combat" class="room-left-column">
                    <section v-if="panelState.library" class="room-panel library-panel">
                        <header class="room-panel-heading">
                            <h2><BookOpen :size="14" /> Biblioteca</h2>
                            <button class="tiny-icon-button" type="button" aria-label="Adicionar item à biblioteca"><span>+</span></button>
                        </header>
                        <label class="library-search">
                            <Search :size="13" />
                            <input v-model="search" aria-label="Procurar na biblioteca" placeholder="Procurar na biblioteca" />
                        </label>
                        <div class="library-tree">
                            <button type="button"><ChevronDown :size="12" /><BookOpen :size="14" /> Regras da mesa</button>
                            <button type="button" class="tree-child"><BookOpen :size="13" /> Guia do aventureiro</button>
                            <button type="button"><ChevronDown :size="12" /><BookOpen :size="14" /> Cenário</button>
                            <button type="button" class="tree-child"><Swords :size="13" /> Encontro na cripta</button>
                            <button v-if="search" class="tree-hint" type="button">Filtrar: {{ search }}</button>
                        </div>
                    </section>

                    <section v-if="panelState.macros" class="room-panel macros-panel">
                        <header class="room-panel-heading">
                            <h2><Dices :size="14" /> Macros</h2>
                            <button class="tiny-icon-button" type="button" aria-label="Gerenciar macros"><Ellipsis :size="14" /></button>
                        </header>
                        <form class="macro-create-form" @submit.prevent="createMacro">
                            <input v-model="macroName" aria-label="Nome da nova macro" maxlength="40" placeholder="Nome" required />
                            <textarea v-model="macroBody" aria-label="Ações da nova macro" maxlength="3000" rows="2" placeholder="Mensagem ou /r por linha" required></textarea>
                            <select v-model="macroScope" aria-label="Escopo da nova macro">
                                <option value="personal_room">Pessoal nesta mesa</option>
                                <option value="personal_global">Pessoal global</option>
                                <option v-if="can_manage" value="shared_room">Compartilhada da mesa</option>
                            </select>
                            <button class="macro-manage" type="submit"><Settings :size="12" /> Criar macro</button>
                        </form>
                        <p v-if="macroError" class="field-error" role="alert">{{ macroError }}</p>
                        <div v-for="macro in macroList" :key="macro.id" class="macro-row-wrap">
                            <button class="macro-row" type="button" :aria-label="`Executar macro ${macro.name}`" @click="runMacro(macro)">
                                <span>{{ macro.name }} <small>{{ macro.scope === 'shared_room' ? 'Mesa' : macro.scope === 'personal_global' ? 'Global' : 'Pessoal' }}</small></span><Dices :size="12" />
                            </button>
                            <button
                                v-if="macro.scope !== 'shared_room' || can_manage"
                                class="tiny-icon-button"
                                type="button"
                                :aria-label="`Editar macro ${macro.name}`"
                                @click="editMacro(macro)"
                            ><Settings :size="12" /></button>
                            <button
                                v-if="macro.scope !== 'shared_room' || can_manage"
                                class="tiny-icon-button"
                                type="button"
                                :aria-label="`Excluir macro ${macro.name}`"
                                @click="deleteMacro(macro)"
                            ><Trash2 :size="12" /></button>
                        </div>
                    </section>

                    <section v-if="panelState.combat" class="room-panel combat-panel">
                        <header class="room-panel-heading">
                            <h2><Swords :size="14" /> Combate</h2>
                            <button class="tiny-icon-button" type="button" aria-label="Opções de combate"><Ellipsis :size="14" /></button>
                        </header>
                        <div class="combat-tools">
                            <button type="button">Teste</button>
                            <button type="button">Init</button>
                            <button type="button">A/H</button>
                        </div>
                        <div class="combat-empty"><span class="combat-indicator"></span> Aguardando iniciativa</div>
                        <footer><button type="button">Próx. turno</button><span>Rodada <strong>1</strong></span></footer>
                    </section>
                </aside>

                <section class="room-panel chat-panel" aria-label="Conversa da mesa">
                    <header class="room-panel-heading chat-heading">
                        <h2><MessageSquare :size="14" /> Conversa</h2>
                        <button class="tiny-icon-button" type="button" aria-label="Opções da conversa"><Ellipsis :size="15" /></button>
                    </header>

                    <div class="chat-history" aria-live="polite">
                        <button v-if="historyBefore" class="chat-load-older" type="button" :disabled="historyLoading" @click="loadOlderMessages">
                            {{ historyLoading ? 'Carregando…' : 'Carregar mensagens anteriores' }}
                        </button>
                        <div class="chat-date-divider"><span>Hoje</span></div>
                        <article
                            v-for="item in messageList"
                            :key="item.command_id ?? item.id"
                            class="chat-message"
                            :class="[authorClass(item.author), `chat-message-${item.message_type ?? 'public'}`, { 'chat-message-pending': item.pending }]"
                        >
                            <span class="chat-avatar" :class="authorClass(item.author)">{{ item.author.slice(0, 1) }}</span>
                            <div class="chat-message-content">
                                <header>
                                    <strong>{{ item.author }}</strong>
                                    <small v-if="item.message_type === 'private'">{{ item.author_id === user_id ? `Para ${item.recipient}` : 'Mensagem privada' }}</small>
                                    <time>{{ timestamp(item.created_at) }}</time>
                                </header>
                                <template v-if="item.message_type === 'roll'">
                                    <p><strong>/r {{ item.expression }} = {{ item.total }}</strong></p>
                                    <small v-for="(component, index) in item.components" :key="index">
                                        {{ component.kind === 'dice' ? `${component.count}d${component.sides}: ${component.results?.join(', ')}` : `constante ${component.value}` }}
                                        ({{ component.subtotal >= 0 ? '+' : '' }}{{ component.subtotal }})
                                    </small>
                                </template>
                                <p v-else v-html="formatMessageBody(item.body)"></p>
                                <a v-for="asset in item.assets ?? []" :key="asset.id" class="chat-asset-link" :href="asset.url" target="_blank" rel="noreferrer">
                                    <Paperclip :size="11" /> {{ asset.name }}
                                </a>
                            </div>
                        </article>
                        <div v-if="!messageList.length" class="chat-empty">A conversa da mesa começa aqui.</div>
                    </div>

                    <form class="chat-composer" @submit.prevent="sendMessage">
                        <div class="chat-message-options">
                            <label>
                                <span class="sr-only">Tipo da mensagem</span>
                                <select v-model="messageType" aria-label="Tipo da mensagem" @change="recipientId = null">
                                    <option value="public">Pública</option>
                                    <option value="narration">Narração</option>
                                    <option value="private">Privada</option>
                                </select>
                            </label>
                            <label v-if="messageType === 'private'">
                                <span class="sr-only">Destinatário</span>
                                <select v-model.number="recipientId" aria-label="Destinatário da mensagem privada" required>
                                    <option :value="null" disabled>Escolher participante</option>
                                    <option v-for="member in members.filter((item) => item.id !== user_id)" :key="member.id" :value="member.id">{{ member.name }}</option>
                                </select>
                            </label>
                            <label v-if="library_assets.length" class="chat-asset-select">
                                <span class="sr-only">Anexar imagem da biblioteca</span>
                                <select v-model="selectedAssetIds" multiple aria-label="Anexar imagens da biblioteca">
                                    <option v-for="asset in library_assets" :key="asset.id" :value="asset.id">{{ asset.name }}</option>
                                </select>
                            </label>
                        </div>
                        <textarea
                            ref="messageEditor"
                            v-model="messageBody"
                            aria-label="Digite uma mensagem"
                            placeholder="Digite uma mensagem..."
                            rows="2"
                            maxlength="2000"
                            @keydown.enter.exact.prevent="sendMessage"
                        ></textarea>
                        <div class="composer-toolbar">
                            <div class="formatting-tools">
                                <button type="button" aria-label="Negrito" @click="wrapMessageSelection('**')"><strong>B</strong></button>
                                <button class="italic-tool" type="button" aria-label="Itálico" @click="wrapMessageSelection('*')">I</button>
                                <button class="underline-tool" type="button" aria-label="Sublinhado" @click="wrapMessageSelection('__')">U</button>
                                <span class="toolbar-divider"></span>
                                <button type="button" aria-label="Anexar arquivo"><Paperclip :size="14" /></button>
                                <button type="button" aria-label="Inserir rolagem" @click="messageType = 'public'; messageBody = messageBody.trim() ? `${messageBody}\n/r 1d20` : '/r 1d20'"><Dices :size="15" /></button>
                                <button type="button" aria-label="Opções de áudio"><Music :size="14" /></button>
                            </div>
                            <button class="send-message" type="submit" aria-label="Enviar mensagem" :disabled="!messageBody.trim() || (messageType === 'private' && !recipientId)">
                                <Send :size="14" />
                            </button>
                        </div>
                        <p v-if="messageError" class="field-error" role="alert">{{ messageError }}</p>
                    </form>
                </section>

                <aside v-if="panelState.participants" class="room-panel participants-panel">
                    <header class="room-panel-heading participant-heading">
                        <h2><Users :size="14" /> Participantes <span>{{ members.length }}</span></h2>
                        <button class="tiny-icon-button" type="button" aria-label="Opções de participantes"><Ellipsis :size="15" /></button>
                    </header>
                    <div class="participant-group-label"><span>Mestre</span><i></i></div>
                    <article v-for="member in members.filter((item) => item.role !== 'player')" :key="member.id" class="participant-card">
                        <span class="participant-avatar avatar-master">{{ member.name.slice(0, 1) }}</span>
                        <div class="participant-details">
                            <strong>{{ member.name }}</strong>
                            <span class="participant-role"><Shield :size="10" /> {{ member.role === 'owner' ? 'Proprietário · Mestre' : 'Mestre' }}</span>
                            <div class="health-bars"><span></span><span></span><span></span></div>
                            <small>{{ onlineMembers.includes(member.id) ? 'Conectado agora' : 'Na mesa' }}</small>
                        </div>
                    </article>

                    <div class="participant-group-label players-label"><span>Jogadores</span><i></i></div>
                    <article v-for="member in members.filter((item) => item.role === 'player')" :key="member.id" class="participant-card">
                        <span class="participant-avatar">{{ member.name.slice(0, 1) }}</span>
                        <div class="participant-details">
                            <strong>{{ member.name }}</strong>
                            <span class="participant-role">Jogador</span>
                            <div class="health-bars"><span></span><span></span><span></span></div>
                            <small>{{ onlineMembers.includes(member.id) ? 'Conectado agora' : 'Na mesa' }}</small>
                        </div>
                    </article>

                    <button v-if="room.is_owner" class="invite-player" type="button" @click="copyInviteLink">
                        <Users :size="13" /> Convidar jogadores
                    </button>
                    <div class="participant-note"><LockKeyhole :size="11" /> Apenas participantes veem esta mesa.</div>
                </aside>
            </div>
        </section>
    </AppLayout>
</template>
