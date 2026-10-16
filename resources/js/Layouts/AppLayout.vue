<script setup lang="ts">
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Clock3,
    Gamepad2,
    House,
    LogOut,
    Plus,
    Settings,
    Star,
    Users,
} from '@lucide/vue';
import type { PageProps } from '@/types';

defineProps<{
    roomTitle?: string;
    roomId?: number;
}>();

const page = usePage<PageProps>();
const logout = useForm({});
const currentPath = computed(() => page.url.split('?')[0]);

const navigation = [
    { label: 'Início', href: '/', icon: House },
    { label: 'Mesas online', href: '/mesas', icon: Gamepad2 },
    { label: 'Favoritas', href: '/mesas?section=favoritas', icon: Star },
    { label: 'Perfil', href: '/profile', icon: Settings },
];
</script>

<template>
    <div class="application-shell">
        <header class="windowbar">
            <Link class="window-brand" href="/" aria-label="Freecast, início">
                <span class="brand-mark"><Gamepad2 :size="15" /></span>
                <span>Freecast</span>
                <span class="window-caption">· mesa virtual</span>
            </Link>
            <div class="window-user">
                <span class="connection-dot"></span>
                <span>{{ page.props.auth.user.name }}</span>
                <button
                    class="window-logout"
                    type="button"
                    aria-label="Sair da conta"
                    title="Sair da conta"
                    @click="logout.post('/logout')"
                >
                    <LogOut :size="13" />
                </button>
            </div>
        </header>

        <div class="application-body">
            <aside class="navigation-rail" aria-label="Navegação principal">
                <nav class="rail-links">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="rail-link"
                        :class="{ 'is-active': currentPath === item.href.split('?')[0] }"
                        :aria-label="item.label"
                        :title="item.label"
                    >
                        <component :is="item.icon" :size="18" />
                    </Link>
                </nav>
                <Link
                    class="rail-profile"
                    href="/profile"
                    :aria-label="`Perfil de ${page.props.auth.user.name}`"
                    :title="page.props.auth.user.name"
                >
                    {{ page.props.auth.user.name.slice(0, 1).toUpperCase() }}
                </Link>
            </aside>

            <div class="application-workspace">
                <nav v-if="currentPath !== '/'" class="tab-strip" aria-label="Abas abertas">
                    <Link class="top-tab" :class="{ 'is-current': !roomTitle && currentPath === '/' }" href="/">
                        <House :size="13" />
                        <span>Início</span>
                    </Link>
                    <Link
                        v-if="roomTitle && roomId"
                        class="top-tab"
                        :class="{ 'is-current': currentPath === `/mesas/${roomId}` }"
                        :href="`/mesas/${roomId}`"
                    >
                        <span class="tab-status"></span>
                        <span class="tab-name">Mesa: {{ roomTitle }}</span>
                        <span class="tab-close" aria-hidden="true">×</span>
                    </Link>
                    <Link class="new-tab" href="/mesas/nova" aria-label="Criar mesa" title="Criar mesa">
                        <Plus :size="16" />
                    </Link>
                    <div class="tab-actions">
                        <slot name="tab-actions" />
                        <Link href="/mesas" aria-label="Explorar mesas" title="Explorar mesas">
                            <Users :size="15" />
                        </Link>
                        <Link href="/profile" aria-label="Preferências" title="Preferências">
                            <Settings :size="15" />
                        </Link>
                    </div>
                </nav>

                <main class="page-viewport">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
