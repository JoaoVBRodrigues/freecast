<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RoomCard from '@/Components/RoomCard.vue';
import type { RoomSummary } from '@/types/rooms';
import { Head, Link } from '@inertiajs/vue3';
import { Compass, Plus, Search, Star, Users } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    rooms: RoomSummary[];
    section: string;
}>();

const search = ref('');
const sections = [
    { key: 'online', label: 'Online', icon: Users },
    { key: 'recentes', label: 'Recentes', icon: Compass },
    { key: 'favoritas', label: 'Favoritas', icon: Star },
];

const visibleRooms = computed(() => {
    let rooms = props.rooms;
    if (props.section === 'favoritas') rooms = rooms.filter((room) => room.is_favorite);
    if (props.section === 'recentes') rooms = rooms.filter((room) => room.is_member);
    const term = search.value.trim().toLocaleLowerCase();
    return term ? rooms.filter((room) => `${room.title} ${room.system}`.toLocaleLowerCase().includes(term)) : rooms;
});
</script>

<template>
    <Head title="Mesas online" />
    <AppLayout>
        <div class="page-content discovery-page">
            <header class="page-heading">
                <div><h1>Mesas online</h1><p>Encontre uma história ou reúna seus amigos.</p></div>
                <Link class="button-primary" href="/mesas/nova"><Plus :size="15" /> Criar mesa</Link>
            </header>

            <div class="discovery-layout">
                <aside class="discovery-sidebar">
                    <div class="subnav-title">MESAS</div>
                    <Link
                        v-for="section in sections"
                        :key="section.key"
                        :href="`/mesas?section=${section.key}`"
                        class="discovery-nav-item"
                        :class="{ 'is-active': props.section === section.key }"
                    >
                        <component :is="section.icon" :size="15" />
                        {{ section.label }}
                    </Link>
                    <Link class="discovery-nav-item discovery-create" href="/mesas/nova"><Plus :size="15" /> Nova mesa</Link>
                </aside>

                <section class="discovery-results">
                    <div class="discovery-toolbar">
                        <div>
                            <h2>{{ sections.find((item) => item.key === props.section)?.label ?? 'Online' }}</h2>
                            <span>{{ visibleRooms.length }} {{ visibleRooms.length === 1 ? 'mesa disponível' : 'mesas disponíveis' }}</span>
                        </div>
                        <label class="search-box">
                            <Search :size="15" />
                            <input v-model="search" aria-label="Procurar mesas" placeholder="Procurar mesas..." />
                        </label>
                    </div>
                    <div v-if="visibleRooms.length" class="discovery-grid">
                        <RoomCard v-for="room in visibleRooms" :key="room.id" :room="room" />
                    </div>
                    <div v-else class="empty-panel">
                        <Users :size="29" />
                        <strong>Nenhuma mesa nesta lista.</strong>
                        <span>Crie uma aventura ou escolha outra seção.</span>
                        <Link href="/mesas/nova">Criar mesa</Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
