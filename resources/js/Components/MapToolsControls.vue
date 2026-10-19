<script setup lang="ts">
import type { MapTool } from '@/composables/useMapTools';
defineProps<{ tool: MapTool; color: string; width: number; measurement: string; canManage: boolean; fogEnabled: boolean; busy: boolean; selected: number | null; canDelete: boolean; scale: number; unit: string; error: string }>();
defineEmits(['update:tool', 'update:color', 'update:width', 'update:scale', 'update:unit', 'fog', 'remove', 'scale', 'cancel']);
const tools: Array<[MapTool, string]> = [['select','Selecionar'], ['ruler','Régua'], ['line','Linha'], ['rectangle','Retângulo'], ['ellipse','Elipse'], ['freehand','Livre']];
</script>
<template>
    <section class="map-tools" aria-label="Ferramentas do mapa">
        <div class="map-tools-row" role="group" aria-label="Ferramenta ativa">
            <button v-for="[key,label] in tools" :key="key" type="button" :aria-pressed="tool === key" :disabled="busy" @click="$emit('update:tool',key)">{{ label }}</button>
            <button type="button" @click="$emit('cancel')">Cancelar (Esc)</button>
        </div>
        <div class="map-tools-row">
            <label>Cor <select :value="color" aria-label="Cor do desenho" @change="$emit('update:color',($event.target as HTMLSelectElement).value)"><option v-for="[c,label] in [['#eb3449','Vermelho'],['#ffffff','Branco'],['#ffd166','Amarelo'],['#55c1ff','Azul'],['#67d391','Verde'],['#b692ff','Violeta']]" :key="c" :value="c">{{ label }}</option></select></label>
            <label>Traço <select :value="width" aria-label="Espessura do desenho" @change="$emit('update:width',Number(($event.target as HTMLSelectElement).value))"><option v-for="w in [2,4,8,12]" :key="w" :value="w">{{ w }}</option></select></label>
            <button v-if="selected !== null" type="button" :disabled="!canDelete || busy" @click="$emit('remove')">Remover desenho</button>
        </div>
        <output data-testid="ruler-measurement" aria-live="polite">{{ measurement || (tool === 'ruler' ? 'Arraste entre dois pontos · distância euclidiana' : '') }}</output>
        <details v-if="canManage"><summary>Escala e névoa manual</summary>
            <form class="map-tools-row" @submit.prevent="$emit('scale')">
                <label>1 célula = <input :value="scale" aria-label="Escala da régua" type="number" min="0.001" max="1000000" step="0.001" required @input="$emit('update:scale',Number(($event.target as HTMLInputElement).value))" /></label>
                <label>Unidade <input :value="unit" aria-label="Unidade da régua" maxlength="20" required @input="$emit('update:unit',($event.target as HTMLInputElement).value)" /></label>
                <button :disabled="busy">Salvar escala</button>
            </form>
            <div class="map-tools-row">
                <button type="button" :disabled="busy" @click="$emit('fog',fogEnabled ? 'disable' : 'enable')">{{ fogEnabled ? 'Desativar névoa' : 'Ativar névoa' }}</button>
                <button v-for="[key,label] in [['reveal','Revelar região'],['cover','Ocultar região']]" :key="key" type="button" :aria-pressed="tool === key" :disabled="!fogEnabled || busy" @click="$emit('update:tool',key)">{{ label }}</button>
                <button type="button" :disabled="busy" @click="$emit('fog','reveal_all')">Revelar tudo</button>
                <button type="button" :disabled="busy" @click="$emit('fog','cover_all')">Ocultar tudo</button>
            </div>
            <small>Máscara de 64 unidades. Tokens: visibilidade pelo centro. Fundo completo e recursos públicos podem ser inspecionados.</small>
        </details>
        <p v-if="error" role="alert">{{ error }}</p>
    </section>
</template>
<style scoped>
.map-tools { padding: 5px 8px; background: #242424; border-bottom: 1px solid #414141; font-size: 11px; }
.map-tools-row { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin: 3px 0; }
button, select, input { background: #333; color: #eee; border: 1px solid #555; padding: 3px 5px; border-radius: 3px; font: inherit; }
button[aria-pressed=true] { background: #9b2634; border-color: #eb3449; }
button:disabled { opacity: .4; } input { width: 70px; } output { color: #ffd166; } summary { cursor: pointer; } small { color: #bbb; }
</style>
