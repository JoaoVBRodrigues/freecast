<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { LockKeyhole, Shield, Star, Swords, Users } from '@lucide/vue';
import type { RoomSummary } from '@/types/rooms';

const props = defineProps<{
    room: RoomSummary;
    compact?: boolean;
}>();

const password = ref('');
const showPassword = ref(false);
const joining = ref(false);

const joinRoom = () => {
    joining.value = true;
    router.post(`/mesas/${props.room.id}/entrar`, { password: password.value }, {
        onFinish: () => {
            joining.value = false;
            password.value = '';
        },
    });
};
</script>

<template>
    <article class="room-card" :class="{ 'room-card-compact': compact }">
        <Link
            v-if="room.is_member"
            class="room-card-open"
            :href="`/mesas/${room.id}`"
            :aria-label="`Abrir mesa ${room.title}`"
        >
            <div class="room-art" :class="`room-art-${room.id % 4}`">
                <div class="art-orbit art-orbit-one"></div>
                <div class="art-orbit art-orbit-two"></div>
                <Swords :size="compact ? 27 : 34" />
            </div>
            <div class="room-card-copy">
                <strong>{{ room.title }}</strong>
                <span>{{ room.system }}</span>
                <small>{{ room.member_count }} {{ room.member_count === 1 ? 'jogador' : 'jogadores' }} na mesa</small>
            </div>
        </Link>
        <template v-else>
            <div class="room-card-open">
                <div class="room-art" :class="`room-art-${room.id % 4}`">
                    <div class="art-orbit art-orbit-one"></div>
                    <div class="art-orbit art-orbit-two"></div>
                    <Swords :size="compact ? 27 : 34" />
                </div>
                <div class="room-card-copy">
                    <strong>{{ room.title }}</strong>
                    <span>{{ room.system }}</span>
                    <small>{{ room.member_count }} {{ room.member_count === 1 ? 'jogador' : 'jogadores' }} na mesa</small>
                </div>
            </div>
        </template>

        <button
            class="room-favorite"
            type="button"
            :aria-label="room.is_favorite ? 'Remover dos favoritos' : 'Favoritar mesa'"
            :title="room.is_favorite ? 'Remover dos favoritos' : 'Adicionar aos favoritos'"
            @click="router.post(`/mesas/${room.id}/favorita`)"
        >
            <Star :size="15" :fill="room.is_favorite ? 'currentColor' : 'none'" />
        </button>

        <div class="room-card-status">
            <span><span class="status-dot"></span>{{ room.owner_name }}</span>
            <span v-if="room.requires_password" class="status-lock">
                <LockKeyhole :size="12" /> Requer senha
            </span>
            <span v-else-if="room.is_owner"><Shield :size="12" /> Minha mesa</span>
            <span v-else><Users :size="12" /> Mesa aberta</span>
        </div>

        <div class="room-card-actions">
            <Link v-if="room.is_member" class="button-secondary button-small" :href="`/mesas/${room.id}`">
                Abrir mesa
            </Link>
            <template v-else-if="room.requires_password">
                <button
                    v-if="!showPassword"
                    class="button-secondary button-small"
                    type="button"
                    @click="showPassword = true"
                >
                    Entrar
                </button>
                <form v-else class="room-join-form" @submit.prevent="joinRoom">
                    <input
                        v-model="password"
                        aria-label="Senha da mesa"
                        autocomplete="off"
                        placeholder="Senha da mesa"
                        type="password"
                    />
                    <button class="button-primary button-small" type="submit" :disabled="joining">
                        {{ joining ? 'Entrando…' : 'Confirmar' }}
                    </button>
                </form>
            </template>
            <button v-else class="button-primary button-small" type="button" :disabled="joining" @click="joinRoom">
                {{ joining ? 'Entrando…' : 'Entrar na mesa' }}
            </button>
        </div>
    </article>
</template>
