<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
const props = defineProps<{roomId:number}>();
interface Preview { id:string; title:string; image_url:string; expires_at:string; width:number; height:number; cell_size:number; counts:{walls:number;portals:number;lights:number;points:number}; warnings:string[] }
const imageLoaded=ref(false);
const open=ref(false), busy=ref(false), error=ref(''), file=ref<File|null>(null), preview=ref<Preview|null>(null);
function message(e:unknown) { return axios.isAxiosError(e) ? Object.values(e.response?.data?.errors ?? {}).flat().join(' ') || e.response?.data?.message || 'Falha na importação; tente novamente.' : 'Falha na importação.'; }
async function prepare() {
    if (!file.value || busy.value) return;
    busy.value=true; error.value='';
    try { const body=new FormData(); body.append('file',file.value); const r=await axios.post(`/mesas/${props.roomId}/importacoes/dd2vtt`,body); imageLoaded.value=false; preview.value=r.data.preview; }
    catch(e) { error.value=message(e); } finally { busy.value=false; }
}
async function cancel() {
    busy.value=true; error.value='';
    try { if (preview.value) await axios.delete(`/mesas/${props.roomId}/importacoes/dd2vtt/${preview.value.id}`); preview.value=null; file.value=null; open.value=false; }
    catch(e) {
        if (axios.isAxiosError(e) && (e.response?.status===410 || e.response?.status===404)) { preview.value=null; file.value=null; open.value=false; }
        else error.value=message(e);
    } finally { busy.value=false; }
}
async function confirm() {
    if (!preview.value || busy.value || !imageLoaded.value) return;
    busy.value=true; error.value='';
    try { const r=await axios.post(`/mesas/${props.roomId}/importacoes/dd2vtt/${preview.value.id}/confirmar`); router.visit(r.data.url); }
    catch(e) { error.value=message(e); } finally { busy.value=false; }
}
</script>
<template>
    <section class="map-library-section dd2vtt-importer">
        <button v-if="!open" class="button-secondary button-small" @click="open=true">Importar dd2vtt</button>
        <div v-else>
            <h2>Importar dd2vtt</h2>
            <form v-if="!preview" @submit.prevent="prepare">
                <label>Arquivo dd2vtt <input type="file" aria-label="Arquivo dd2vtt" accept=".dd2vtt" required :disabled="busy" @change="file=($event.target as HTMLInputElement).files?.[0]??null" /></label>
                <p>PNG/WebP embutido; até 29 MiB por arquivo e 20 MiB de imagem. O mapa existente não será substituído.</p>
                <button type="submit" class="button-primary button-small" :disabled="busy">Validar e preparar prévia</button>
            </form>
            <div v-else data-testid="dd2vtt-preview">
                <h3>{{ preview.title }}</h3>
                <img :src="preview.image_url" alt="Prévia do mapa importado" @load="imageLoaded=true" @error="imageLoaded=false;error='Imagem não pôde ser exibida; cancele e escolha outro arquivo.'" style="max-width:100%;max-height:220px;object-fit:contain" />
                <p>{{ preview.width }} × {{ preview.height }} · grade {{ preview.cell_size }} px/célula</p>
                <p>{{ preview.counts.walls }} paredes · {{ preview.counts.portals }} portais · {{ preview.counts.lights }} luzes · {{ preview.counts.points }} pontos</p>
                <ul><li v-for="warning in preview.warnings" :key="warning">{{ warning }}</li></ul>
                <p>Novo mapa começa com névoa totalmente coberta. Não será definido como mapa ativo. Prévia expira em {{ new Date(preview.expires_at).toLocaleTimeString('pt-BR') }}.</p>
                <button class="button-primary button-small" :disabled="busy || !imageLoaded" @click="confirm">Confirmar importação e abrir novo mapa</button>
            </div>
            <p v-if="error" role="alert" class="field-error">{{ error }}</p>
            <button class="button-secondary button-small" :disabled="busy" @click="cancel">Cancelar importação</button>
        </div>
    </section>
</template>
