<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
} from '@lucide/vue';
import { nextTick, reactive, ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';

interface RoomMember {
    id: number;
    name: string;
    role: 'owner' | 'master' | 'player';
}

interface RoomMessage {
    id: number;
    author: string;
    body: string;
    created_at: string;
}

const props = defineProps<{
    room: { id: number; title: string; system: string; description: string; owner_name: string; is_owner: boolean };
    members: RoomMember[];
    messages: RoomMessage[];
    panels: Record<string, boolean>;
}>();

const panelState = reactive({ ...props.panels });
const showPanelMenu = ref(false);
const search = ref('');
const messageList = ref<RoomMessage[]>([...props.messages]);
const composer = useForm({ body: '' });
const panels = [
    { key: 'library', label: 'Exibir Biblioteca da mesa' },
    { key: 'macros', label: 'Exibir Macros' },
    { key: 'combat', label: 'Exibir Tracker de Combate' },
    { key: 'participants', label: 'Exibir participantes' },
];

const message = (event: RoomMessage) => {
    if (!messageList.value.some((item) => item.id === event.id)) {
        messageList.value.push(event);
        nextTick(() => {
            const list = document.querySelector('.chat-history');
            if (list) list.scrollTop = list.scrollHeight;
        });
    }
};

useEcho<RoomMessage>(
    `rooms.${props.room.id}`,
    '.room.message.posted',
    message,
    [],
    'private',
);

const togglePanel = (key: string) => {
    panelState[key] = !panelState[key];
    router.patch(`/mesas/${props.room.id}/preferencias`, { panels: { ...panelState } }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const sendMessage = () => {
    composer.post(`/mesas/${props.room.id}/mensagens`, {
        preserveScroll: true,
        onSuccess: () => composer.reset('body'),
    });
};

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
                        <button class="macro-manage" type="button"><Settings :size="12" /> Gerenciar Macros</button>
                        <button class="macro-row" type="button"><span>/r 1d20</span><Dices :size="12" /></button>
                        <button class="macro-row" type="button"><span>Teste</span><Dices :size="12" /></button>
                        <button class="macro-row" type="button"><span>Iniciativa</span><Dices :size="12" /></button>
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
                        <div class="chat-date-divider"><span>Hoje</span></div>
                        <article v-for="item in messageList" :key="item.id" class="chat-message" :class="authorClass(item.author)">
                            <span class="chat-avatar" :class="authorClass(item.author)">{{ item.author.slice(0, 1) }}</span>
                            <div class="chat-message-content">
                                <header><strong>{{ item.author }}</strong><time>{{ timestamp(item.created_at) }}</time></header>
                                <p>{{ item.body }}</p>
                            </div>
                        </article>
                        <div v-if="!messageList.length" class="chat-empty">A conversa da mesa começa aqui.</div>
                    </div>

                    <form class="chat-composer" @submit.prevent="sendMessage">
                        <textarea
                            v-model="composer.body"
                            aria-label="Digite uma mensagem"
                            placeholder="Digite uma mensagem..."
                            rows="2"
                            maxlength="2000"
                            @keydown.enter.exact.prevent="sendMessage"
                        ></textarea>
                        <div class="composer-toolbar">
                            <div class="formatting-tools">
                                <button type="button" aria-label="Negrito"><strong>B</strong></button>
                                <button class="italic-tool" type="button" aria-label="Itálico">I</button>
                                <button class="underline-tool" type="button" aria-label="Sublinhado">U</button>
                                <span class="toolbar-divider"></span>
                                <button type="button" aria-label="Anexar arquivo"><Paperclip :size="14" /></button>
                                <button type="button" aria-label="Inserir rolagem" @click="composer.body = `${composer.body}/r 1d20`"><Dices :size="15" /></button>
                                <button type="button" aria-label="Opções de áudio"><Music :size="14" /></button>
                            </div>
                            <button class="send-message" type="submit" aria-label="Enviar mensagem" :disabled="composer.processing || !composer.body.trim()">
                                <Send :size="14" />
                            </button>
                        </div>
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
                            <small>Presente na mesa</small>
                        </div>
                    </article>

                    <div class="participant-group-label players-label"><span>Jogadores</span><i></i></div>
                    <article v-for="member in members.filter((item) => item.role === 'player')" :key="member.id" class="participant-card">
                        <span class="participant-avatar">{{ member.name.slice(0, 1) }}</span>
                        <div class="participant-details">
                            <strong>{{ member.name }}</strong>
                            <span class="participant-role">Jogador</span>
                            <div class="health-bars"><span></span><span></span><span></span></div>
                            <small>Conectado à aventura</small>
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
