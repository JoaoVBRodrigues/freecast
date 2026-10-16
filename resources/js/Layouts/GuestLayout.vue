<script setup lang="ts">
import { Gamepad2 } from '@lucide/vue';
</script>

<template>
    <div class="guest-shell">
        <header class="guest-brandbar">Freecast <span class="window-caption">· mesa virtual</span></header>
        <main class="guest-content">
            <section class="auth-card">
                <div class="auth-brand">
                    <span class="auth-brand-icon"><Gamepad2 :size="21" /></span>
                    <div><strong>Freecast</strong><span>Seu grupo, sua próxima aventura.</span></div>
                </div>
                <slot />
            </section>
        </main>
    </div>
</template>
