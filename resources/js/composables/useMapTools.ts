import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { screenToWorld, snapWorldPointToGrid, type Camera, type Point } from '@/mapCoordinates';
import type Konva from 'konva';

export interface ToolMap {
    id: number; width: number; height: number; cell_size: number; revision: number;
    fog_enabled: boolean; fog_revealed: number[]; distance_scale: number; distance_unit: string;
}
export interface Drawing {
    id: number; kind: string; points: Point[]; color: string; stroke_width: number; revision: number; can_delete: boolean;
}
export type MapTool = 'select' | 'ruler' | 'line' | 'rectangle' | 'ellipse' | 'freehand' | 'reveal' | 'cover';

export function useMapTools(props: { room: { id: number }; map: ToolMap; can_manage: boolean; drawings: Drawing[] }, camera: Camera, snapping: { value: boolean }, space: { value: boolean }) {
    const tool = ref<MapTool>('select');
    const color = ref('#eb3449'); const width = ref(4);
    const points = ref<Point[]>([]); const drawing = ref(false); const selected = ref<number | null>(null);
    const error = ref(''); const busy = ref(false);
    const scale = ref(props.map.distance_scale); const unit = ref(props.map.distance_unit);
    watch(() => props.map, m => { scale.value = m.distance_scale; unit.value = m.distance_unit; });
    const cancel = () => { points.value = []; drawing.value = false; selected.value = null; };
    watch(tool, cancel);
    watch(() => props.drawings, drawings => { if (!drawings.some(d => d.id === selected.value)) selected.value = null; });
    const measurement = computed(() => {
        if (points.value.length < 2 || tool.value !== 'ruler') return '';
        const a = points.value[0], b = points.value.at(-1)!;
        const cells = Math.hypot(b.x - a.x, b.y - a.y) / props.map.cell_size;
        return `${Number(cells.toFixed(3))} células · ${Number((cells * props.map.distance_scale).toFixed(3))} ${props.map.distance_unit}`;
    });
    async function command(input: Record<string, unknown>) {
        if (busy.value) return;
        busy.value = true; error.value = '';
        try {
            await axios.post(`/mesas/${props.room.id}/mapas/${props.map.id}/ferramentas`, { expected_revision: props.map.revision, idempotency_key: crypto.randomUUID(), ...input });
            await new Promise<void>(resolve => router.reload({ only: ['map', 'layers', 'drawings'], onFinish: () => resolve() }));
        } catch (e) {
            error.value = axios.isAxiosError(e) && e.response?.status === 409 ? 'Mapa alterado. Atualize e refaça a operação.' : 'Operação recusada. Verifique área, limites e permissão.';
            router.reload({ only: ['map', 'layers', 'drawings'] });
        } finally { busy.value = false; }
    }
    function position(e: Konva.KonvaEventObject<MouseEvent>): Point | null {
        const p = e.target.getStage()?.getPointerPosition(); if (!p) return null;
        let world = screenToWorld(p, camera);
        if (snapping.value && tool.value !== 'freehand') world = snapWorldPointToGrid(world, props.map.cell_size);
        return { x: Math.max(0, Math.min(props.map.width, world.x)), y: Math.max(0, Math.min(props.map.height, world.y)) };
    }
    function start(e: Konva.KonvaEventObject<MouseEvent>) {
        if (tool.value === 'select' || space.value || busy.value || e.evt.button !== 0) return;
        const p = position(e); if (!p) return;
        drawing.value = true; points.value = [p, p]; e.evt.preventDefault();
    }
    function move(e: Konva.KonvaEventObject<MouseEvent>) {
        if (!drawing.value || space.value) return;
        const p = position(e); if (!p) return;
        if (tool.value === 'freehand') {
            const last = points.value.at(-1)!;
            if (points.value.length < 256 && Math.hypot(last.x-p.x, last.y-p.y) >= 2) points.value.push(p);
        } else points.value = [points.value[0], p];
    }
    async function finish(e: Konva.KonvaEventObject<MouseEvent>) {
        if (!drawing.value) return;
        move(e); drawing.value = false;
        if (tool.value === 'ruler') return;
        const a = points.value[0], b = points.value.at(-1)!;
        if (Math.hypot(a.x-b.x, a.y-b.y) < 1 && tool.value !== 'freehand') { points.value = []; return; }
        if (tool.value === 'reveal' || tool.value === 'cover') {
            if (a.x === b.x || a.y === b.y) return;
            await command({ action: 'fog', mode: tool.value, rect: { x: Math.min(a.x,b.x), y: Math.min(a.y,b.y), width: Math.abs(b.x-a.x), height: Math.abs(b.y-a.y) } });
        } else await command({ action: 'draw', kind: tool.value, points: points.value, color: color.value, stroke_width: width.value });
        points.value = [];
    }
    async function fog(mode: string) {
        if ((mode === 'reveal_all' || mode === 'cover_all') && !window.confirm(mode === 'reveal_all' ? 'Revelar todo o mapa para os jogadores?' : 'Ocultar todo o mapa dos jogadores?')) return;
        await command({ action: 'fog', mode, ...(mode.endsWith('_all') ? { confirmed: true } : {}) });
    }
    async function remove() {
        const item = props.drawings.find(d => d.id === selected.value); if (!item || !item.can_delete) return;
        await command({ action: 'delete', drawing_id: item.id, drawing_revision: item.revision }); selected.value = null;
    }
    const leave = () => { if (drawing.value) cancel(); };
    return { tool, color, width, points, selected, error, busy, scale, unit, measurement, cancel, leave, start, move, finish, fog, remove, command };
}
