<script setup lang="ts">
import { computed } from 'vue';
import type { Drawing, MapTool, ToolMap } from '@/composables/useMapTools';
import type { Point } from '@/mapCoordinates';
const props = defineProps<{ map: ToolMap; drawings: Drawing[]; tool: MapTool; points: Point[]; color: string; width: number; selected: number | null; master: boolean; panning: boolean }>();
defineEmits<{ select: [id: number] }>();
function shape(d: Pick<Drawing,'points'|'kind'|'color'|'stroke_width'>) {
    const a = d.points[0], b = d.points.at(-1)!;
    const base = { stroke: d.color, strokeWidth: d.stroke_width, hitStrokeWidth: Math.max(12,d.stroke_width), fillEnabled: false };
    if (d.kind === 'rectangle') return { ...base, x: Math.min(a.x,b.x), y: Math.min(a.y,b.y), width: Math.abs(b.x-a.x), height: Math.abs(b.y-a.y) };
    if (d.kind === 'ellipse') return { ...base, x: (a.x+b.x)/2, y: (a.y+b.y)/2, radiusX: Math.abs(b.x-a.x)/2, radiusY: Math.abs(b.y-a.y)/2 };
    return { ...base, points: d.points.flatMap(p => [p.x,p.y]), lineCap: 'round', lineJoin: 'round' };
}
const preview = computed(() => props.points.length >= 2 ? shape({ kind: ['reveal','cover'].includes(props.tool) ? 'rectangle' : props.tool, points: props.points, color: props.tool === 'cover' ? '#b692ff' : props.color, stroke_width: props.width }) : null);
const fogRuns = computed(() => {
    if (!props.map.fog_enabled) return [];
    const result: Array<{x:number;y:number;width:number;height:number}> = [];
    const revealed = new Set(props.map.fog_revealed); const columns = Math.ceil(props.map.width/64), rows = Math.ceil(props.map.height/64);
    for (let y=0;y<rows;y++) {
        let start=-1;
        for(let x=0;x<=columns;x++) {
            const covered=x<columns && !revealed.has(y*columns+x);
            if(covered && start<0) start=x;
            if(!covered && start>=0) { result.push({ x:start*64,y:y*64,width:Math.min(x*64,props.map.width)-start*64,height:Math.min(64,props.map.height-y*64) }); start=-1; }
        }
    }
    return result;
});
const component = (kind:string) => kind === 'rectangle' ? 'v-rect' : kind === 'ellipse' ? 'v-ellipse' : 'v-line';
</script>
<template>
    <v-group>
        <component :is="component(d.kind)" v-for="d in drawings" :key="d.id" :config="{ ...shape(d), name:`drawing-${d.id}`, opacity: selected === d.id ? 0.6 : 1, listening: tool === 'select' && !panning }" @click="$emit('select',d.id)" />
        <v-rect v-for="(r,i) in fogRuns" :key="`fog-${i}`" :config="{ ...r, fill: master ? '#69527b' : '#080808', opacity: master ? 0.48 : 1, listening: false }" />
        <component :is="component(['reveal','cover'].includes(tool) ? 'rectangle' : tool)" v-if="preview" :config="{ ...preview, listening:false }" />
        <v-rect v-if="tool !== 'select' && !panning" :config="{ x:0,y:0,width:map.width,height:map.height,fill:'rgba(0,0,0,0.001)',listening:true }" />
    </v-group>
</template>
