<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import type { MapAsset, MapFolder, RoomMapSummary } from '@/types/maps';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Copy, FolderPlus, ImagePlus, Map, Pencil, Plus, Trash2 } from '@lucide/vue';

const props = defineProps<{
    room: { id: number; title: string };
    can_manage: boolean;
    maps: RoomMapSummary[];
    folders: MapFolder[];
    assets: MapAsset[];
}>();

const newMap = useForm({ title: 'Novo mapa', width: 1600, height: 1000, cell_size: 50 });
const newFolder = useForm({ name: '', parent_id: null as number | null, is_master_only: false });
const upload = useForm({
    file: null as File | null,
    name: '',
    kind: 'background' as 'background' | 'token',
    folder_id: null as number | null,
    is_master_only: false,
});
const folderNames = Object.fromEntries(props.folders.map((folder) => [folder.id, folder.name]));

const submitMap = () => newMap.post(`/mesas/${props.room.id}/mapas`);
const submitFolder = () => newFolder.post(`/mesas/${props.room.id}/biblioteca/pastas`, {
    preserveScroll: true,
    onSuccess: () => newFolder.reset('name', 'parent_id', 'is_master_only'),
});
const renameFolder = (folder: MapFolder) => {
    const name = folderNames[folder.id]?.trim();
    if (name && name !== folder.name) {
        router.patch(`/mesas/${props.room.id}/biblioteca/pastas/${folder.id}`, { name }, { preserveScroll: true });
    }
};
const removeFolder = (folder: MapFolder) => {
    router.delete(`/mesas/${props.room.id}/biblioteca/pastas/${folder.id}`, { preserveScroll: true });
};
const submitUpload = () => upload.post(`/mesas/${props.room.id}/biblioteca/recursos`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => upload.reset('file', 'name'),
});
const duplicateMap = (map: RoomMapSummary) => router.post(`/mesas/${props.room.id}/mapas/${map.id}/duplicar`);
const removeMap = (map: RoomMapSummary) => {
    if (window.confirm(`Excluir o mapa “${map.title}” e seus tokens?`)) {
        router.delete(`/mesas/${props.room.id}/mapas/${map.id}`);
    }
};
const removeAsset = (asset: MapAsset) => {
    if (window.confirm(`Excluir “${asset.name}” da biblioteca?`)) {
        router.delete(`/mesas/${props.room.id}/biblioteca/recursos/${asset.id}`, { preserveScroll: true });
    }
};
const renameMap = (map: RoomMapSummary, event: SubmitEvent) => {
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement)) return;
    const input = form.elements.namedItem('title');
    if (input instanceof HTMLInputElement && input.value.trim()) {
        router.patch(`/mesas/${props.room.id}/mapas/${map.id}`, { title: input.value.trim() }, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`Mapas — ${room.title}`" />
    <AppLayout :room-title="room.title" :room-id="room.id">
        <div class="map-library-page">
            <header class="map-library-heading">
                <div>
                    <Link class="subtle-link" :href="`/mesas/${room.id}`"><ArrowLeft :size="13" /> Voltar à conversa</Link>
                    <h1><Map :size="18" /> Mapas e biblioteca</h1>
                    <p>{{ room.title }} · mapas, fundos e tokens da mesa</p>
                </div>
                <Link class="button-secondary button-small" :href="`/mesas/${room.id}`">Conversa</Link>
            </header>

            <section class="map-library-section">
                <div class="map-library-section-heading">
                    <h2>Mapas</h2>
                    <span>{{ maps.length }} mapa(s)</span>
                </div>
                <form v-if="can_manage" class="map-create-form" @submit.prevent="submitMap">
                    <label>
                        <span>Nome do mapa</span>
                        <input v-model="newMap.title" aria-label="Nome do mapa" maxlength="100" required />
                    </label>
                    <label>
                        <span>Largura</span>
                        <input v-model.number="newMap.width" aria-label="Largura do mapa" type="number" min="200" max="8000" required />
                    </label>
                    <label>
                        <span>Altura</span>
                        <input v-model.number="newMap.height" aria-label="Altura do mapa" type="number" min="200" max="8000" required />
                    </label>
                    <label>
                        <span>Célula</span>
                        <input v-model.number="newMap.cell_size" aria-label="Tamanho da célula" type="number" min="8" max="256" required />
                    </label>
                    <button class="button-primary button-small" type="submit" :disabled="newMap.processing">
                        <Plus :size="13" /> Criar mapa
                    </button>
                </form>
                <div v-if="maps.length" class="map-card-grid">
                    <article v-for="map in maps" :key="map.id" class="map-library-card">
                        <Link class="map-card-preview" :href="`/mesas/${room.id}/mapas/${map.id}`" :aria-label="`Abrir mapa ${map.title}`">
                            <img v-if="map.background" :src="map.background" :alt="`Fundo de ${map.title}`" />
                            <Map v-else :size="28" />
                            <span>{{ map.width }} × {{ map.height }}</span>
                        </Link>
                        <div class="map-library-card-copy">
                            <Link :href="`/mesas/${room.id}/mapas/${map.id}`"><strong>{{ map.title }}</strong></Link>
                            <span>Atualizado {{ new Date(map.updated_at).toLocaleDateString('pt-BR') }}</span>
                            <details v-if="can_manage" class="map-card-actions">
                                <summary aria-label="Ações do mapa"><Pencil :size="13" /> Ações</summary>
                                <form class="map-inline-rename" @submit.prevent="renameMap(map, $event)">
                                    <input name="title" :value="map.title" aria-label="Renomear mapa" maxlength="100" />
                                    <button type="submit">Salvar nome</button>
                                </form>
                                <button type="button" @click="duplicateMap(map)"><Copy :size="12" /> Duplicar</button>
                                <button type="button" class="danger-text" @click="removeMap(map)"><Trash2 :size="12" /> Excluir</button>
                            </details>
                        </div>
                    </article>
                </div>
                <div v-else class="map-library-empty">Ainda não há mapas nesta mesa.</div>
                <p v-if="!can_manage" class="map-library-note">Você pode abrir mapas e mover tokens atribuídos a você.</p>
            </section>

            <section class="map-library-section map-assets-section">
                <div class="map-library-section-heading">
                    <h2>Biblioteca de recursos</h2>
                    <span>{{ assets.length }} imagem(ns)</span>
                </div>
                <form v-if="can_manage" class="asset-upload-form" @submit.prevent="submitUpload">
                    <label class="asset-file-field">
                        <span>Imagem de mapa ou token</span>
                        <input
                            aria-label="Imagem de mapa ou token"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/gif"
                            required
                            @input="upload.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                    </label>
                    <label>
                        <span>Nome</span>
                        <input v-model="upload.name" aria-label="Nome do recurso" maxlength="120" placeholder="Nome opcional" />
                    </label>
                    <label>
                        <span>Tipo</span>
                        <select v-model="upload.kind" aria-label="Tipo de recurso">
                            <option value="background">Fundo</option>
                            <option value="token">Token</option>
                        </select>
                    </label>
                    <label>
                        <span>Pasta</span>
                        <select v-model.number="upload.folder_id" aria-label="Pasta do recurso">
                            <option :value="null">Sem pasta</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                        </select>
                    </label>
                    <label class="asset-private-check">
                        <input v-model="upload.is_master_only" type="checkbox" />
                        Exclusivo do mestre
                    </label>
                    <button class="button-primary button-small" type="submit" :disabled="upload.processing">
                        <ImagePlus :size="13" /> Enviar
                    </button>
                    <p v-if="upload.errors.file" class="field-error">{{ upload.errors.file }}</p>
                </form>

                <details v-if="can_manage" class="folder-manager">
                    <summary><FolderPlus :size="14" /> Organizar pastas</summary>
                    <form class="folder-create-form" @submit.prevent="submitFolder">
                        <input v-model="newFolder.name" aria-label="Nome da pasta" placeholder="Nome da pasta" maxlength="80" required />
                        <select v-model.number="newFolder.parent_id" aria-label="Pasta superior">
                            <option :value="null">Raiz</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                        </select>
                        <label><input v-model="newFolder.is_master_only" type="checkbox" /> Exclusiva do mestre</label>
                        <button class="button-secondary button-small" type="submit">Criar pasta</button>
                    </form>
                </details>

                <div v-if="assets.length" class="asset-library-grid">
                    <article v-for="asset in assets" :key="asset.id" class="asset-library-card">
                        <img :src="asset.url" :alt="asset.name" />
                        <div>
                            <strong>{{ asset.name }}</strong>
                            <span>{{ asset.kind === 'background' ? 'Fundo' : 'Token' }} · {{ asset.width }} × {{ asset.height }}</span>
                            <small v-if="asset.is_master_only">Visível somente ao mestre</small>
                        </div>
                        <button v-if="can_manage" class="tiny-icon-button danger-text" type="button" :aria-label="`Excluir ${asset.name}`" @click="removeAsset(asset)">
                            <Trash2 :size="13" />
                        </button>
                    </article>
                </div>
                <div v-else class="map-library-empty">Envie imagens próprias para usar nos mapas.</div>

                <ul v-if="can_manage && folders.length" class="folder-list">
                    <li v-for="folder in folders" :key="folder.id">
                        <form @submit.prevent="renameFolder(folder)">
                            <input v-model="folderNames[folder.id]" :aria-label="`Renomear pasta ${folder.name}`" maxlength="80" />
                            <button class="button-secondary button-small" type="submit">Renomear</button>
                        </form>
                        <span v-if="folder.is_master_only">Mestre</span>
                        <button class="tiny-icon-button danger-text" type="button" :aria-label="`Excluir pasta ${folder.name}`" @click="removeFolder(folder)">
                            <Trash2 :size="13" />
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
