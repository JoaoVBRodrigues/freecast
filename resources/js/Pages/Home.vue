<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RoomCard from '@/Components/RoomCard.vue';
import type { RoomSummary } from '@/types/rooms';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Compass, MessageCircle, Plus, Sparkles, UserRoundCog } from '@lucide/vue';

defineProps<{
    featured: RoomSummary | null;
    recent: RoomSummary[];
    favorites: RoomSummary[];
    owned: RoomSummary[];
}>();

const actions = [
    { title: 'Explorar mesas', detail: 'Encontre uma aventura para jogar.', href: '/mesas', icon: Compass, color: 'blue' },
    { title: 'Criar mesa', detail: 'Prepare a próxima sessão.', href: '/mesas/nova', icon: Plus, color: 'red' },
    { title: 'Conversar com amigos', detail: 'Suas mesas e seus grupos.', href: '/mesas', icon: MessageCircle, color: 'teal' },
    { title: 'Personalizar conta', detail: 'Ajuste seu perfil de jogador.', href: '/profile', icon: UserRoundCog, color: 'violet' },
];
</script>

<template>
    <Head title="Início" />
    <AppLayout>
        <div class="page-content home-page">
            <header class="page-heading">
                <div>
                    <h1>Início</h1>
                    <p>Suas aventuras, de volta à mesa.</p>
                </div>
                <Link class="button-primary" href="/mesas/nova"><Plus :size="15" /> Nova mesa</Link>
            </header>

            <section class="home-section">
                <div class="section-heading">
                    <div>
                        <h2>Recomendações</h2>
                        <span>Mesas abertas para conhecer</span>
                    </div>
                    <Link class="subtle-link" href="/mesas">Ver mesas online <span>›</span></Link>
                </div>

                <div class="home-feature-grid">
                    <article v-if="featured" class="featured-room">
                        <div class="featured-art">
                            <span class="featured-moon"></span>
                            <BookOpen :size="43" :stroke-width="1.25" />
                            <span class="featured-stamp">AVENTURA<br />ABERTA</span>
                        </div>
                        <div class="featured-copy">
                            <span class="eyebrow"><Sparkles :size="12" /> MESA EM DESTAQUE</span>
                            <h3>{{ featured.title }}</h3>
                            <p class="featured-system">{{ featured.system }} · {{ featured.member_count }} jogadores</p>
                            <p class="featured-description">{{ featured.description }}</p>
                            <div class="featured-footer">
                                <span><span class="status-dot"></span> {{ featured.owner_name }}</span>
                                <RoomCard :room="featured" compact />
                            </div>
                        </div>
                    </article>
                    <div v-else class="featured-empty">
                        <Sparkles :size="26" />
                        <strong>Sua próxima história começa aqui.</strong>
                        <span>Crie uma mesa ou explore as aventuras abertas.</span>
                    </div>

                    <div class="action-grid">
                        <Link v-for="action in actions" :key="action.title" class="action-tile" :class="`action-${action.color}`" :href="action.href">
                            <span class="action-icon"><component :is="action.icon" :size="23" /></span>
                            <strong>{{ action.title }}</strong>
                            <small>{{ action.detail }}</small>
                        </Link>
                    </div>
                </div>
            </section>

            <section class="home-section card-section">
                <div class="section-heading">
                    <div><h2>Recentes</h2><span>Mesas que você visitou</span></div>
                    <Link class="subtle-link" href="/mesas?section=recentes">Ver recentes <span>›</span></Link>
                </div>
                <div v-if="recent.length" class="room-card-grid">
                    <RoomCard v-for="room in recent" :key="room.id" :room="room" />
                </div>
                <div v-else class="empty-strip">Suas mesas visitadas aparecem aqui.</div>
            </section>

            <section class="home-section card-section">
                <div class="section-heading">
                    <div><h2>Favoritas</h2><span>Guardadas para mais tarde</span></div>
                    <Link class="subtle-link" href="/mesas?section=favoritas">Ver favoritas <span>›</span></Link>
                </div>
                <div v-if="favorites.length" class="room-card-grid">
                    <RoomCard v-for="room in favorites" :key="room.id" :room="room" />
                </div>
                <div v-else class="empty-strip">Marque uma mesa com a estrela para encontrá-la aqui.</div>
            </section>

            <section class="home-section card-section">
                <div class="section-heading">
                    <div><h2>Minhas</h2><span>Mesas que você conduz</span></div>
                    <Link class="subtle-link" href="/mesas">Ver todas <span>›</span></Link>
                </div>
                <div v-if="owned.length" class="room-card-grid">
                    <RoomCard v-for="room in owned" :key="room.id" :room="room" />
                </div>
                <div v-else class="empty-strip">
                    Ainda não criou uma mesa.
                    <Link href="/mesas/nova">Preparar uma aventura <span>›</span></Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
