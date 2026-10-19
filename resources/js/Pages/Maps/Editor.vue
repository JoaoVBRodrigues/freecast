<script setup lang="ts">
import MapToolsControls from '@/Components/MapToolsControls.vue';
import MapToolsOverlay from '@/Components/MapToolsOverlay.vue';
import { useMapTools, type Drawing, type ToolMap } from '@/composables/useMapTools';
import CharacterPanel from '@/Components/CharacterPanel.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { MapAsset, MapLayer, MapObject } from '@/types/maps';
import { screenToWorld, snapWorldPointToGrid, worldToScreen, zoomAtPoint, type Camera, type Point } from '@/mapCoordinates';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowDown, ArrowLeft, ArrowUp, Eye, EyeOff, Grid2X2, LockKeyhole, LockKeyholeOpen, MapPinned, Minus, Move, Plus, Scan, Trash2, Unlock, UserRound, ZoomIn, ZoomOut } from '@lucide/vue';
import Konva from 'konva';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, shallowReactive, shallowRef, toRaw, watch } from 'vue';
import type { VueKonvaRef } from 'vue-konva';
import { echo, useEcho, useEchoPresence } from '@laravel/echo-vue';

interface MapData extends ToolMap {
    id: number;
    revision: number;
    title: string;
    width: number;
    height: number;
    cell_size: number;
    grid_color: string;
    grid_opacity: number;
    grid_enabled: boolean;
    background_asset_id: number | null;
    background_url: string | null;
}

interface RoomSyncEnvelope {
    eventId: string;
    stream: 'room' | 'masters' | 'user';
    sequence: number;
    type: string;
    payload: Record<string, unknown>;
}

interface ObjectPreview {
    roomId: number;
    mapId: number;
    objectId: number;
    x: number;
    y: number;
    revision: number;
    userId: number;
}

const props = defineProps<{
    room: { id: number; title: string };
    map: MapData;
    can_manage: boolean;
    user_id: number;
    drawings: Drawing[];
    layers: MapLayer[];
    assets: MapAsset[];
    assignable_members: Array<{ id: number; name: string }>;
}>();

const characterVersion = ref(0);
const canvasHost = ref<HTMLElement | null>(null);
const worldGroup = ref<VueKonvaRef<Konva.Group> | null>(null);
const transformer = ref<VueKonvaRef<Konva.Transformer> | null>(null);
const viewport = reactive({ width: 720, height: 420 });
const camera = reactive<Camera>({ x: 0, y: 0, scale: 1 });
const spaceDown = ref(false);
const snapping = ref(true);
const tools = useMapTools(props, camera, snapping, spaceDown);
const { tool, color: drawingColor, width: drawingWidth, points: toolPoints, selected: selectedDrawing, error: toolError, busy: toolBusy, scale: distanceScale, unit: distanceUnit, measurement } = tools;
watch(tool, () => { selectedObjectId.value = null; selectedNode.value = null; });
const selectedObjectId = ref<number | null>(null);
const selectedNode = shallowRef<Konva.Node | null>(null);
const loadedImages = shallowReactive<Record<string, HTMLImageElement>>({});
const loadedImageUrls = reactive<Record<string, string>>({});
const imageErrors = reactive<Record<string, string>>({});
const editorError = ref('');
const settings = reactive({
    width: props.map.width,
    height: props.map.height,
    cell_size: props.map.cell_size,
    grid_color: props.map.grid_color,
    grid_opacity: props.map.grid_opacity,
    grid_enabled: props.map.grid_enabled,
    background_asset_id: props.map.background_asset_id,
});

function copyLayerState(layers: MapLayer[]): MapLayer[] {
    return toRaw(layers).map((layer) => ({
        ...toRaw(layer),
        objects: toRaw(layer.objects).map((object) => ({ ...toRaw(object) })),
    }));
}

const liveLayers = ref<MapLayer[]>(copyLayerState(props.layers));
const remotePreviews = reactive<Record<number, { x: number; y: number }>>({});
const previewSentAt = new Map<number, number>();
const eventCursors = reactive({ room: 0, masters: 0, user: 0 });
const receivedEvents = new Set<string>();

const localCameraKey = computed(() => `freecast:map-camera:${props.user_id}:${props.map.id}`);
const worldGroupConfig = computed(() => ({
    x: camera.x,
    y: camera.y,
    scaleX: camera.scale,
    scaleY: camera.scale,
    draggable: false,
}));
const stageConfig = computed(() => ({
    width: viewport.width,
    height: viewport.height,
    listening: true,
}));
const orderedLayers = computed(() => [...liveLayers.value].sort((a, b) => a.sort_order - b.sort_order));
const drawableObjects = computed(() =>
    orderedLayers.value.flatMap((layer) => layer.visible
        ? [...layer.objects]
            .filter((object) => object.visible && object.url)
            .sort((a, b) => a.z_index - b.z_index)
        : []),
);
const allObjects = computed(() => orderedLayers.value.flatMap((layer) => layer.objects));
const selectedObject = computed(() => allObjects.value.find((item) => item.id === selectedObjectId.value) ?? null);
const tokenLayer = computed(() => liveLayers.value.find((layer) => layer.type === 'tokens') ?? null);
const gmLayer = computed(() => liveLayers.value.find((layer) => layer.type === 'gm') ?? null);
const backgroundAssets = computed(() => props.assets.filter((asset) => asset.kind === 'background' && !asset.is_master_only));
const tokenAssets = computed(() => props.assets.filter((asset) => asset.kind === 'token'));
const selectedCanTransform = computed(() => props.can_manage && Boolean(selectedObject.value?.visible));
const transformerConfig = computed(() => ({
    nodes: selectedCanTransform.value && selectedNode.value ? [selectedNode.value] : [],
    rotateEnabled: selectedCanTransform.value,
    keepRatio: true,
    enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
    anchorSize: 8,
    borderStroke: '#eb3449',
    anchorStroke: '#f3f3f3',
    anchorFill: '#eb3449',
    padding: 2,
}));
const gridLines = computed(() => {
    if (!settings.grid_enabled) return [];
    const lines: Array<{ key: string; points: number[] }> = [];
    for (let x = 0; x <= settings.width; x += settings.cell_size) {
        lines.push({ key: `x-${x}`, points: [x, 0, x, settings.height] });
    }
    for (let y = 0; y <= settings.height; y += settings.cell_size) {
        lines.push({ key: `y-${y}`, points: [0, y, settings.width, y] });
    }
    return lines;
});

watch(
    () => props.map,
    (map) => Object.assign(settings, {
        width: map.width,
        height: map.height,
        cell_size: map.cell_size,
        grid_color: map.grid_color,
        grid_opacity: map.grid_opacity,
        grid_enabled: map.grid_enabled,
        background_asset_id: map.background_asset_id,
    }),
);

watch(
    () => props.layers,
    (layers) => {
        liveLayers.value = copyLayerState(layers);
    },
    { deep: true },
);

watch(
    () => [props.map.background_url, ...drawableObjects.value.map((object) => `${object.id}:${object.url}`)],
    async () => {
        await nextTick();
        loadVisibleImages();
    },
    { immediate: true },
);

watch(selectedNode, async (node) => {
    await nextTick();
    const transformerNode = transformer.value?.getNode();
    if (!transformerNode) return;
    transformerNode.nodes(node && selectedCanTransform.value ? [node] : []);
    transformerNode.getLayer()?.batchDraw();
});

watch(selectedCanTransform, (enabled) => {
    if (!enabled) selectedNode.value = null;
});

const presenceEvents = useEchoPresence<RoomSyncEnvelope>(
    `rooms.${props.room.id}`,
    '.room.state.changed',
    applyRoomEvent,
);
presenceEvents.channel().here(() => void recoverRoomEvents());

useEchoPresence<ObjectPreview>(
    `rooms.${props.room.id}`,
    '.room.object.preview',
    applyObjectPreview,
);

if (props.can_manage) {
    useEcho<RoomSyncEnvelope>(`rooms.${props.room.id}.masters`, '.room.state.changed', applyRoomEvent, [], 'private');
    useEcho<ObjectPreview>(`rooms.${props.room.id}.masters`, '.room.object.preview', applyObjectPreview, [], 'private');
}
useEcho<RoomSyncEnvelope>(`users.${props.user_id}`, '.room.state.changed', applyRoomEvent, [], 'private');

const stopObservingConnection = echo().connector.onConnectionChange((status) => {
    if (status === 'connected') { characterVersion.value++; void recoverRoomEvents(); }
});
onBeforeUnmount(stopObservingConnection);

function applyRoomEvent(event: RoomSyncEnvelope): void {
    eventCursors[event.stream] = Math.max(eventCursors[event.stream], event.sequence);
    if (receivedEvents.has(event.eventId)) return;
    receivedEvents.add(event.eventId);
    if (event.type === 'characters.changed') { characterVersion.value++; router.reload({ only: ['layers'] }); }
    // Sheet visibility is global: a covered representation on another map also
    // removes its name/PV from tokens and open sheet/combat panels on this map.
    if (event.type.startsWith('map.') && Number(event.payload.map_id) !== props.map.id) {
        characterVersion.value++;
        selectedObjectId.value = null; selectedNode.value = null;
        Object.keys(remotePreviews).forEach(id => delete remotePreviews[Number(id)]);
        router.reload({ only: ['layers'] });
    }
    if (
        ['map.contents.changed', 'map.updated', 'map.layer.updated', 'map.object.created', 'map.object.updated', 'map.object.removed'].includes(event.type)
        && Number(event.payload.map_id) === props.map.id
    ) {
        selectedObjectId.value = null; selectedNode.value = null; selectedDrawing.value = null;
        Object.keys(remotePreviews).forEach(id => delete remotePreviews[Number(id)]);
        characterVersion.value++;
        router.reload({ only: ['map', 'layers', 'assets', 'drawings'] });
    }
}

function applyObjectPreview(preview: ObjectPreview): void {
    if (
        preview.mapId !== props.map.id
        || preview.userId === props.user_id
        || !allObjects.value.some((object) => object.id === preview.objectId)
    ) {
        return;
    }

    remotePreviews[preview.objectId] = { x: preview.x, y: preview.y };
    window.setTimeout(() => {
        delete remotePreviews[preview.objectId];
    }, 1200);
}

async function recoverRoomEvents(): Promise<void> {
    try {
        let hasMore = true;
        while (hasMore) {
            const response = await axios.get(`/mesas/${props.room.id}/eventos`, {
                params: { after: { ...eventCursors } },
                headers: { Accept: 'application/json' },
            });
            const result = response.data as { events: RoomSyncEnvelope[]; cursor: typeof eventCursors; hasMore: boolean };
            result.events.forEach(applyRoomEvent);
            eventCursors.room = Math.max(eventCursors.room, result.cursor.room ?? 0);
            eventCursors.masters = Math.max(eventCursors.masters, result.cursor.masters ?? 0);
            eventCursors.user = Math.max(eventCursors.user, result.cursor.user ?? 0);
            hasMore = result.hasMore;
        }
    } catch (error) {
        editorError.value = 'A conexão foi retomada, mas não foi possível recuperar todas as alterações.';
        console.error('Falha ao recuperar eventos do mapa.', error);
    }
}

function loadImage(url: string | null, key: string): void {
    if (!url) {
        delete loadedImages[key];
        delete loadedImageUrls[key];
        delete imageErrors[key];
        return;
    }
    if (loadedImageUrls[key] === url) return;
    delete loadedImages[key];
    delete imageErrors[key];
    loadedImageUrls[key] = url;
    const image = new Image();
    image.onload = () => {
        if (loadedImageUrls[key] !== url) return;
        loadedImages[key] = image;
        delete imageErrors[key];
    };
    image.onerror = () => {
        if (loadedImageUrls[key] !== url) return;
        imageErrors[key] = 'Não foi possível carregar a imagem autorizada do mapa.';
    };
    image.src = url;
}

function loadVisibleImages(): void {
    loadImage(props.map.background_url, 'background');
    for (const object of drawableObjects.value) {
        if (object.url) loadImage(object.url, `object-${object.id}`);
    }
}

function saveCameraPreferences(): void {
    localStorage.setItem(localCameraKey.value, JSON.stringify({ camera: { ...camera }, snapping: snapping.value }));
}

function restoreCameraPreferences(): boolean {
    const stored = localStorage.getItem(localCameraKey.value);
    if (!stored) return false;

    try {
        const value: unknown = JSON.parse(stored);
        if (
            typeof value === 'object'
            && value !== null
            && 'camera' in value
            && typeof value.camera === 'object'
            && value.camera !== null
            && 'x' in value.camera
            && 'y' in value.camera
            && 'scale' in value.camera
            && typeof value.camera.x === 'number'
            && typeof value.camera.y === 'number'
            && typeof value.camera.scale === 'number'
            && Number.isFinite(value.camera.x)
            && Number.isFinite(value.camera.y)
            && Number.isFinite(value.camera.scale)
            && value.camera.scale >= 0.08
            && value.camera.scale <= 5
        ) {
            Object.assign(camera, value.camera);
            if ('snapping' in value && typeof value.snapping === 'boolean') snapping.value = value.snapping;
            return true;
        }
    } catch (error) {
        console.warn('Preferência local de câmera inválida; o mapa será enquadrado novamente.', error);
    }

    localStorage.removeItem(localCameraKey.value);
    return false;
}

function updateViewport(): void {
    const bounds = canvasHost.value?.getBoundingClientRect();
    if (!bounds) return;
    viewport.width = Math.max(1, Math.floor(bounds.width));
    viewport.height = Math.max(1, Math.floor(bounds.height));
}

function fitMap(): void {
    updateViewport();
    const padding = 36;
    const scale = Math.min(
        (viewport.width - padding * 2) / props.map.width,
        (viewport.height - padding * 2) / props.map.height,
        1,
    );
    camera.scale = Math.max(0.08, Math.min(5, scale));
    camera.x = (viewport.width - props.map.width * camera.scale) / 2;
    camera.y = (viewport.height - props.map.height * camera.scale) / 2;
    saveCameraPreferences();
}

function zoomAroundViewport(factor: number): void {
    const center = { x: viewport.width / 2, y: viewport.height / 2 };
    Object.assign(camera, zoomAtPoint(camera, center, factor));
    saveCameraPreferences();
}

function handleWheel(event: Konva.KonvaEventObject<WheelEvent>): void {
    event.evt.preventDefault();
    const bounds = canvasHost.value?.getBoundingClientRect();
    if (!bounds) return;
    const pointer: Point = {
        x: event.evt.clientX - bounds.left,
        y: event.evt.clientY - bounds.top,
    };
    const factor = Math.pow(1.0015, -event.evt.deltaY);
    Object.assign(camera, zoomAtPoint(camera, pointer, factor));
    saveCameraPreferences();
}

function handleStageMouseDown(event: Konva.KonvaEventObject<MouseEvent>): void {
    const isEmptyCanvas = event.target === event.target.getStage();
    if (!spaceDown.value || !isEmptyCanvas) return;
    const group = worldGroup.value?.getNode();
    if (!group) return;
    group.draggable(true);
    group.startDrag();
}

function handlePan(event: Konva.KonvaEventObject<DragEvent>): void {
    const target = event.target as Konva.Node;
    if (target !== worldGroup.value?.getNode()) return;
    camera.x = target.x();
    camera.y = target.y();
}

function finishPan(event: Konva.KonvaEventObject<DragEvent>): void {
    const target = event.target as Konva.Node;
    if (target !== worldGroup.value?.getNode()) return;
    camera.x = target.x();
    camera.y = target.y();
    target.draggable(false);
    saveCameraPreferences();
}

function selectObject(object: MapObject, event: Konva.KonvaEventObject<MouseEvent | TouchEvent>): void {
    selectedObjectId.value = object.id;
    selectedNode.value = event.target;
}

function selectObjectFromList(object: MapObject): void {
    selectedObjectId.value = object.id;
    selectedNode.value = null;
}

function handleCanvasClick(event: Konva.KonvaEventObject<MouseEvent>): void {
    if (event.target === event.target.getStage()) {
        selectedObjectId.value = null;
        selectedNode.value = null;
    }
}

function boundWorldPosition(object: MapObject, position: Point): Point {
    if (!snapping.value || !settings.grid_enabled) return position;
    const snapped = snapWorldPointToGrid(position, settings.cell_size);
    return {
        x: Math.max(0, Math.min(settings.width - object.width, snapped.x)),
        y: Math.max(0, Math.min(settings.height - object.height, snapped.y)),
    };
}

function dragBound(object: MapObject, absolutePosition: Point): Point {
    const worldPosition = screenToWorld(absolutePosition, camera);
    return worldToScreen(boundWorldPosition(object, worldPosition), camera);
}

function persistPosition(object: MapObject, node: Konva.Node): void {
    const position = boundWorldPosition(object, { x: node.x(), y: node.y() });
    node.position(position);
    delete remotePreviews[object.id];
    void saveObjectUpdate(object, { x: position.x, y: position.y });
}

function persistTransform(): void {
    const object = selectedObject.value;
    const node = selectedNode.value;
    if (!object || !node || !props.can_manage) return;
    const width = Math.max(8, Math.round(node.width() * node.scaleX()));
    const height = Math.max(8, Math.round(node.height() * node.scaleY()));
    const rotation = Math.round(node.rotation() * 10) / 10;
    node.scale({ x: 1, y: 1 });
    void saveObjectUpdate(object, {
        x: node.x(),
        y: node.y(),
        width,
        height,
        rotation,
    });
}

async function saveObjectUpdate(object: MapObject, values: Record<string, string | number | boolean | null>): Promise<void> {
    try {
        const response = await axios.patch(`/mesas/${props.room.id}/mapas/${props.map.id}/objetos/${object.id}`, {
            ...values,
            expected_revision: object.revision,
            idempotency_key: crypto.randomUUID(),
        }, { headers: { Accept: 'application/json' } });
        object.revision = response.data.revision;
        if (typeof values.x === 'number') object.x = values.x;
        if (typeof values.y === 'number') object.y = values.y;
        if (typeof values.width === 'number') object.width = values.width;
        if (typeof values.height === 'number') object.height = values.height;
        if (typeof values.rotation === 'number') object.rotation = values.rotation;
        editorError.value = '';
    } catch (error) {
        editorError.value = 'Não foi possível salvar a posição. O objeto foi alterado ou sua permissão mudou.';
        console.error('Falha ao salvar alterações no token.', error);
        router.reload({ only: ['layers'] });
    }
}

function sendObjectPreview(object: MapObject, node: Konva.Node): void {
    const now = Date.now();
    const previous = previewSentAt.get(object.id) ?? 0;
    if (now - previous < 600) return;
    previewSentAt.set(object.id, now);
    const position = boundWorldPosition(object, { x: node.x(), y: node.y() });
    void axios.post(`/mesas/${props.room.id}/mapas/${props.map.id}/objetos/${object.id}/preview`, {
        x: position.x,
        y: position.y,
        revision: object.revision,
    }, { headers: { Accept: 'application/json' } }).catch((error: unknown) => {
        console.error('Falha ao enviar a prévia do movimento.', error);
    });
}

function patchMap(values: Partial<MapData>): void {
    if (!props.can_manage) return;
    router.patch(`/mesas/${props.room.id}/mapas/${props.map.id}`, {
        ...values,
        expected_revision: props.map.revision,
        idempotency_key: crypto.randomUUID(),
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            editorError.value = 'Não foi possível salvar as propriedades do mapa.';
        },
        onSuccess: () => {
            editorError.value = '';
        },
    });
}

function updateLayer(layer: MapLayer, values: Partial<Pick<MapLayer, 'visible' | 'locked' | 'sort_order'>>): void {
    if (!props.can_manage) return;
    router.patch(`/mesas/${props.room.id}/mapas/${props.map.id}/camadas/${layer.id}`, {
        ...values,
        expected_revision: layer.revision,
        idempotency_key: crypto.randomUUID(),
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function moveLayer(layer: MapLayer, direction: -1 | 1): void {
    const index = orderedLayers.value.findIndex((item) => item.id === layer.id);
    const targetOrder = index + direction;
    if (targetOrder < 0 || targetOrder >= orderedLayers.value.length) return;
    updateLayer(layer, { sort_order: targetOrder });
}

function addToken(asset: MapAsset): void {
    const layer = asset.is_master_only ? gmLayer.value : tokenLayer.value;
    if (!layer || !props.can_manage) return;
    const existingInLayer = layer.objects.length;
    const columns = Math.max(1, Math.floor(props.map.width / props.map.cell_size));
    const x = (existingInLayer % columns) * props.map.cell_size;
    const y = Math.floor(existingInLayer / columns) * props.map.cell_size;

    router.post(`/mesas/${props.room.id}/mapas/${props.map.id}/objetos`, {
        asset_id: asset.id,
        layer_id: layer.id,
        x,
        y,
        idempotency_key: crypto.randomUUID(),
    }, { preserveScroll: true, preserveState: true });
}

async function removeSelectedObject(object: MapObject): Promise<void> {
    try {
        await axios.delete(`/mesas/${props.room.id}/mapas/${props.map.id}/objetos/${object.id}`, {
            data: { idempotency_key: crypto.randomUUID() },
            headers: { Accept: 'application/json' },
        });
        selectedObjectId.value = null;
        selectedNode.value = null;
    } catch (error) {
        editorError.value = 'Não foi possível remover o objeto.';
        console.error('Falha ao remover objeto do mapa.', error);
    }
}

function updateObject(object: MapObject, values: Record<string, string | number | boolean | null>): void {
    void saveObjectUpdate(object, values);
}

function changeAssignment(object: MapObject, event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    updateObject(object, { assigned_user_id: value ? Number(value) : null });
}

function changeBackground(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const assetId = value ? Number(value) : null;
    settings.background_asset_id = assetId;
    patchMap({ background_asset_id: assetId });
}

function selectedGridPoint(object: MapObject): string {
    const point = worldToScreen({ x: object.x, y: object.y }, camera);
    return `${Math.round(point.x)}, ${Math.round(point.y)}`;
}

function onKeyDown(event: KeyboardEvent): void {
    if (event.code === 'Escape') tools.cancel();
    if (event.code !== 'Space' || event.repeat) return;
    const target = event.target;
    if (target instanceof HTMLElement && target.matches('input, textarea, select, button')) return;
    event.preventDefault();
    spaceDown.value = true;
}

function onKeyUp(event: KeyboardEvent): void {
    if (event.code === 'Space') spaceDown.value = false;
}

function onWindowBlur(): void {
    spaceDown.value = false;
}

let resizeObserver: ResizeObserver | null = null;
onMounted(async () => {
    await nextTick();
    updateViewport();
    if (!restoreCameraPreferences()) fitMap();
    resizeObserver = new ResizeObserver(updateViewport);
    if (canvasHost.value) resizeObserver.observe(canvasHost.value);
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('keyup', onKeyUp);
    window.addEventListener('blur', onWindowBlur);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('keyup', onKeyUp);
    window.removeEventListener('blur', onWindowBlur);
});

watch(() => props.map.id, async () => {
    await nextTick();
    if (!restoreCameraPreferences()) fitMap();
});

</script>

<template>
    <Head :title="`${map.title} — ${room.title}`" />
    <AppLayout :room-title="room.title" :room-id="room.id">
        <section
            class="map-editor-page"
            :data-camera-scale="camera.scale.toFixed(4)"
            :data-camera-x="camera.x.toFixed(2)"
            :data-camera-y="camera.y.toFixed(2)"
            :data-snap-grid="snapping"
            :data-map-revision="map.revision"
            :data-drawing-count="drawings.length"
            :data-fog-enabled="map.fog_enabled"
        >
            <header class="map-editor-toolbar">
                <Link class="map-toolbar-link" :href="`/mesas/${room.id}/mapas`" aria-label="Voltar à biblioteca de mapas">
                    <ArrowLeft :size="15" /><span>Mapas</span>
                </Link>
                <div class="map-toolbar-title">
                    <MapPinned :size="15" />
                    <strong>{{ map.title }}</strong>
                    <span>{{ map.width }} × {{ map.height }}</span>
                </div>
                <Link class="map-toolbar-link map-chat-link" :href="`/mesas/${room.id}`" aria-label="Abrir conversa da mesa">
                    <ArrowLeft :size="13" /> Conversa
                </Link>
                <button type="button" aria-label="Diminuir zoom" title="Diminuir zoom" @click="zoomAroundViewport(1 / 1.2)">
                    <ZoomOut :size="14" />
                </button>
                <span class="map-zoom-label" data-testid="map-zoom">{{ Math.round(camera.scale * 100) }}%</span>
                <button type="button" aria-label="Aumentar zoom" title="Aumentar zoom" @click="zoomAroundViewport(1.2)">
                    <ZoomIn :size="14" />
                </button>
                <button type="button" aria-label="Enquadrar mapa" title="Enquadrar mapa" @click="fitMap">
                    <Scan :size="14" />
                </button>
            </header>

            <div class="map-editor-workspace">
                <div class="map-canvas-column">
                    <MapToolsControls v-model:tool="tool" v-model:color="drawingColor" v-model:width="drawingWidth" v-model:scale="distanceScale" v-model:unit="distanceUnit" :measurement="measurement" :can-manage="can_manage" :fog-enabled="map.fog_enabled" :busy="toolBusy" :selected="selectedDrawing" :can-delete="Boolean(drawings.find(d => d.id === selectedDrawing)?.can_delete)" :error="toolError" @fog="tools.fog" @remove="tools.remove" @cancel="tools.cancel" @scale="tools.command({ action:'scale',distance_scale:distanceScale,distance_unit:distanceUnit })" />
                    <div class="map-canvas-caption">
                        <span><Move :size="12" /> Segure Espaço e arraste para mover a câmera</span>
                        <label><input v-model="snapping" type="checkbox" @change="saveCameraPreferences" /> Encaixe na grade</label>
                    </div>
                    <div
                        ref="canvasHost"
                        class="map-canvas-host"
                        tabindex="0"
                        role="application"
                        aria-label="Canvas do mapa. Tokens selecionáveis; segure Espaço para deslocar a câmera e use a roda do mouse para zoom."
                        @keydown.space.prevent="spaceDown = true"
                        @keyup.space.prevent="spaceDown = false"
                        @blur="spaceDown = false"
                    >
                        <v-stage
                            :config="stageConfig"
                            @wheel="handleWheel"
                            @mousedown="e => { tools.start(e); handleStageMouseDown(e); }"
                            @mousemove="tools.move"
                            @mouseup="tools.finish"
                            @mouseleave="tools.leave"
                            @click="handleCanvasClick"
                            @dragmove="handlePan"
                            @dragend="finishPan"
                        >
                            <v-layer>
                                <v-group ref="worldGroup" :config="worldGroupConfig">
                                    <v-rect :config="{ x: 0, y: 0, width: map.width, height: map.height, fill: '#262626', listening: false, name: 'map-background' }" />
                                    <v-image
                                        v-if="map.background_url && loadedImages.background"
                                        :config="{ x: 0, y: 0, width: map.width, height: map.height, image: loadedImages.background, listening: false, name: 'map-background-image' }"
                                    />
                                    <v-line
                                        v-for="line in gridLines"
                                        :key="line.key"
                                        :config="{
                                            points: line.points,
                                            stroke: settings.grid_color,
                                            strokeWidth: 1 / camera.scale,
                                            opacity: settings.grid_opacity,
                                            listening: false,
                                        }"
                                    />
                                    <v-rect
                                        :config="{
                                            x: 0,
                                            y: 0,
                                            width: map.width,
                                            height: map.height,
                                            stroke: '#eb3449',
                                            strokeWidth: 2 / camera.scale,
                                            listening: false,
                                        }"
                                    />
                                    <v-image
                                        v-for="object in drawableObjects"
                                        :key="object.id"
                                        :config="{
                                            x: remotePreviews[object.id]?.x ?? object.x,
                                            y: remotePreviews[object.id]?.y ?? object.y,
                                            width: object.width,
                                            height: object.height,
                                            rotation: object.rotation,
                                            image: loadedImages[`object-${object.id}`],
                                            draggable: tool === 'select' && object.can_move && !object.locked,
                                            opacity: object.locked ? 0.72 : 1,
                                            name: `map-object-${object.id}`,
                                            dragBoundFunc: (position: Point) => dragBound(object, position),
                                        }"
                                        @click="selectObject(object, $event)"
                                        @tap="selectObject(object, $event)"
                                        @dragstart="selectObject(object, $event)"
                                        @dragmove="sendObjectPreview(object, $event.target)"
                                        @dragend="persistPosition(object, $event.target)"
                                    />
                                    <template v-for="object in drawableObjects.filter(item => item.character)" :key="`hp-${object.id}`">
                                        <v-text :config="{ x: object.x, y: object.y + object.height + 4, text: `${object.character?.name} ${object.character?.hp_current}/${object.character?.hp_max}`, fontSize: 11, fill: '#fff', listening: false }" />
                                        <v-rect :config="{ x: object.x, y: object.y + object.height, width: object.width, height: 3, fill: '#622', listening: false }" />
                                        <v-rect :config="{ x: object.x, y: object.y + object.height, width: object.width * ((object.character?.hp_current ?? 0) / (object.character?.hp_max || 1)), height: 3, fill: '#69b66d', listening: false }" />
                                    </template>
                                    <MapToolsOverlay :map="map" :drawings="drawings" :tool="tool" :points="toolPoints" :color="drawingColor" :width="drawingWidth" :selected="selectedDrawing" :master="can_manage" :panning="spaceDown" @select="id => { selectedDrawing = id; selectedObjectId = null; selectedNode = null; }" />
                                </v-group>
                                <v-transformer
                                    ref="transformer"
                                    :config="transformerConfig"
                                    @transformend="persistTransform"
                                />
                            </v-layer>
                        </v-stage>
                        <div v-if="!map.background_url" class="map-empty-label" aria-hidden="true">
                            <Grid2X2 :size="18" /> Grade vazia · {{ map.width }} × {{ map.height }}
                        </div>
                    </div>
                    <p v-if="imageErrors.background" class="field-error">{{ imageErrors.background }}</p>
                    <p v-if="editorError" class="field-error" role="alert">{{ editorError }}</p>
                </div>

                <aside class="map-inspector" aria-label="Propriedades do mapa e camadas">
                    <section class="map-inspector-section">
                        <header><h2>Desenhos</h2><span>{{ drawings.length }}/500</span></header>
                        <ul class="map-drawing-list">
                            <li v-for="d in drawings" :key="d.id"><button type="button" :data-testid="`drawing-select-${d.id}`" :aria-pressed="selectedDrawing === d.id" @click="selectedDrawing = d.id; selectedObjectId = null; selectedNode = null">{{ ({ line:'Linha', rectangle:'Retângulo', ellipse:'Elipse', freehand:'Livre' } as Record<string,string>)[d.kind] }} · {{ d.stroke_width }} px</button></li>
                        </ul>
                    </section>
                    <section class="map-inspector-section">
                        <header><h2>Camadas</h2><span>{{ layers.length }}</span></header>
                        <ul class="map-layer-list">
                            <li
                                v-for="layer in orderedLayers"
                                :key="layer.id"
                                :data-layer-id="layer.id"
                                :class="{ 'layer-exclusive': layer.type === 'gm' }"
                            >
                                <div>
                                    <strong>{{ layer.name }}</strong>
                                    <small>{{ layer.objects.length }} item(ns)</small>
                                </div>
                                <label class="layer-control" :title="layer.visible ? 'Ocultar camada' : 'Mostrar camada'">
                                    <input
                                        type="checkbox"
                                        :aria-label="`${layer.visible ? 'Ocultar' : 'Mostrar'} camada ${layer.name}`"
                                        :checked="layer.visible"
                                        :disabled="!can_manage"
                                        @change="updateLayer(layer, { visible: !layer.visible })"
                                    />
                                    <Eye v-if="layer.visible" :size="13" /><EyeOff v-else :size="13" />
                                </label>
                                <label class="layer-control" :title="layer.locked ? 'Desbloquear camada' : 'Bloquear camada'">
                                    <input
                                        type="checkbox"
                                        :aria-label="`${layer.locked ? 'Desbloquear' : 'Bloquear'} camada ${layer.name}`"
                                        :checked="layer.locked"
                                        :disabled="!can_manage || layer.type === 'background'"
                                        @change="updateLayer(layer, { locked: !layer.locked })"
                                    />
                                    <LockKeyhole v-if="layer.locked" :size="12" /><LockKeyholeOpen v-else :size="12" />
                                </label>
                                <div v-if="can_manage" class="layer-order-controls" :aria-label="`Ordem da camada ${layer.name}`">
                                    <button
                                        type="button"
                                        :aria-label="`Subir camada ${layer.name}`"
                                        :disabled="orderedLayers[0]?.id === layer.id"
                                        @click="moveLayer(layer, -1)"
                                    ><ArrowUp :size="12" /></button>
                                    <button
                                        type="button"
                                        :aria-label="`Descer camada ${layer.name}`"
                                        :disabled="orderedLayers[orderedLayers.length - 1]?.id === layer.id"
                                        @click="moveLayer(layer, 1)"
                                    ><ArrowDown :size="12" /></button>
                                </div>
                                <span v-if="layer.type === 'gm'" class="master-layer-tag">Mestre</span>
                            </li>
                        </ul>
                        <p class="map-inspector-hint">A ordem e visibilidade das camadas são compartilhadas com a mesa.</p>
                    </section>

                    <section v-if="can_manage" class="map-inspector-section">
                        <header><h2>Fundo e grade</h2></header>
                        <label class="map-setting-field">
                            <span>Imagem de fundo</span>
                            <select :value="settings.background_asset_id ?? ''" aria-label="Imagem de fundo" @change="changeBackground">
                                <option value="">Grade vazia</option>
                                <option v-for="asset in backgroundAssets" :key="asset.id" :value="asset.id">{{ asset.name }}</option>
                            </select>
                        </label>
                        <div class="map-grid-color-row">
                            <label><span>Cor da grade</span><input v-model="settings.grid_color" aria-label="Cor da grade" type="color" @change="patchMap({ grid_color: settings.grid_color })" /></label>
                            <label><span>Opacidade</span><input v-model.number="settings.grid_opacity" aria-label="Opacidade da grade" type="number" min="0.05" max="1" step="0.05" @change="patchMap({ grid_opacity: settings.grid_opacity })" /></label>
                        </div>
                        <label class="map-setting-field">
                            <span>Tamanho da célula ({{ settings.cell_size }} px)</span>
                            <input v-model.number="settings.cell_size" aria-label="Tamanho da célula do mapa" type="range" min="8" max="256" step="1" @change="patchMap({ cell_size: settings.cell_size })" />
                        </label>
                        <label class="map-setting-field">
                            <span>Dimensões do mundo (px)</span>
                            <div class="map-size-inputs">
                                <input v-model.number="settings.width" aria-label="Largura do mundo" type="number" min="200" max="8000" step="50" @change="patchMap({ width: settings.width })" />
                                <span>×</span>
                                <input v-model.number="settings.height" aria-label="Altura do mundo" type="number" min="200" max="8000" step="50" @change="patchMap({ height: settings.height })" />
                            </div>
                        </label>
                        <label class="map-grid-toggle">
                            <input v-model="settings.grid_enabled" type="checkbox" @change="patchMap({ grid_enabled: settings.grid_enabled })" />
                            Mostrar grade
                        </label>
                    </section>

                    <section v-if="can_manage" class="map-inspector-section">
                        <header>
                            <h2>Tokens da biblioteca</h2>
                            <Link class="subtle-link" :href="`/mesas/${room.id}/mapas`" aria-label="Abrir biblioteca de imagens">Abrir</Link>
                        </header>
                        <div v-if="tokenAssets.length" class="map-token-library">
                            <div v-for="asset in tokenAssets" :key="asset.id" class="map-token-choice">
                                <img :src="asset.url" :alt="asset.name" />
                                <span>{{ asset.name }}<small v-if="asset.is_master_only"> · Mestre</small></span>
                                <button
                                    class="tiny-icon-button"
                                    type="button"
                                    :aria-label="`Adicionar token ${asset.name} ao mapa`"
                                    @click="addToken(asset)"
                                >
                                    <Plus :size="13" />
                                </button>
                            </div>
                        </div>
                        <p v-else class="map-inspector-hint">Envie tokens pela biblioteca de imagens.</p>
                    </section>

                    <section class="map-inspector-section map-object-list-section">
                        <header><h2>Itens no mapa</h2><span>{{ allObjects.length }}</span></header>
                        <ul class="map-object-list">
                            <li v-for="layer in orderedLayers" :key="`items-${layer.id}`">
                                <strong>{{ layer.name }}</strong>
                                <button
                                    v-for="object in layer.objects"
                                    :key="object.id"
                                    type="button"
                                    :class="{ 'is-selected': selectedObjectId === object.id, 'is-hidden': !object.visible }"
                                    :data-testid="`map-object-select-${object.id}`"
                                    @click="selectObjectFromList(object)"
                                >
                                    <span>{{ object.name }}<template v-if="object.character"> · PV {{ object.character.hp_current }}/{{ object.character.hp_max }}</template></span>
                                    <small>{{ Math.round(object.x) }}, {{ Math.round(object.y) }}<template v-if="!object.visible"> · oculto</template></small>
                                </button>
                            </li>
                        </ul>
                    </section>

                    <section class="map-inspector-section"><CharacterPanel :room-id="room.id" :can-manage="can_manage" :members="assignable_members" :assets="assets" :version="characterVersion" :token="selectedObject" @changed="router.reload({ only: ['layers'] })" /></section>
                    <section v-if="selectedObject" class="map-inspector-section selected-object-panel">
                        <header><h2>Token selecionado</h2></header>
                        <label class="map-setting-field">
                            <span>Nome</span>
                            <input
                                :value="selectedObject.name"
                                aria-label="Nome do token"
                                maxlength="120"
                                :disabled="!can_manage"
                                @change="updateObject(selectedObject, { name: ($event.target as HTMLInputElement).value })"
                            />
                        </label>
                        <div class="selected-object-position" data-testid="selected-token-position">
                            Mundo {{ Math.round(selectedObject.x) }}, {{ Math.round(selectedObject.y) }}
                            <span> · tela {{ selectedGridPoint(selectedObject) }}</span>
                        </div>
                        <template v-if="can_manage">
                            <label class="map-setting-field">
                                <span>Jogador autorizado</span>
                                <select
                                    :value="selectedObject.assigned_user_id ?? ''"
                                    aria-label="Jogador autorizado a mover o token"
                                    @change="changeAssignment(selectedObject, $event)"
                                >
                                    <option value="">Nenhum</option>
                                    <option v-for="member in assignable_members" :key="member.id" :value="member.id">{{ member.name }}</option>
                                </select>
                            </label>
                            <div class="map-size-inputs">
                                <label><span>Largura</span><input :value="selectedObject.width" aria-label="Largura do token" type="number" min="8" max="2048" @change="updateObject(selectedObject, { width: Number(($event.target as HTMLInputElement).value) })" /></label>
                                <label><span>Altura</span><input :value="selectedObject.height" aria-label="Altura do token" type="number" min="8" max="2048" @change="updateObject(selectedObject, { height: Number(($event.target as HTMLInputElement).value) })" /></label>
                            </div>
                            <label class="map-setting-field">
                                <span>Rotação ({{ Math.round(selectedObject.rotation) }}°)</span>
                                <input :value="selectedObject.rotation" aria-label="Rotação do token" type="range" min="-180" max="180" @input="selectedNode && (selectedNode.rotation(Number(($event.target as HTMLInputElement).value)))" @change="updateObject(selectedObject, { rotation: Number(($event.target as HTMLInputElement).value) })" />
                            </label>
                            <label class="map-grid-toggle">
                                <input :checked="selectedObject.visible" type="checkbox" @change="updateObject(selectedObject, { visible: !selectedObject.visible })" />
                                Visível aos jogadores
                            </label>
                            <label class="map-grid-toggle">
                                <input :checked="selectedObject.locked" type="checkbox" @change="updateObject(selectedObject, { locked: !selectedObject.locked })" />
                                Bloquear token
                            </label>
                            <div class="map-object-actions">
                                <button type="button" @click="updateObject(selectedObject, { z_index: selectedObject.z_index + 1 })"><Plus :size="12" /> Trazer à frente</button>
                                <button type="button" @click="updateObject(selectedObject, { z_index: selectedObject.z_index - 1 })"><Minus :size="12" /> Enviar atrás</button>
                                <button class="danger-text" type="button" @click="removeSelectedObject(selectedObject)"><Trash2 :size="12" /> Remover</button>
                            </div>
                        </template>
                        <p v-else class="map-inspector-hint">
                            <UserRound :size="12" /> {{ selectedObject.can_move ? 'Você pode mover este token.' : 'Este token não foi atribuído a você.' }}
                        </p>
                    </section>

                    <p v-if="!can_manage" class="map-inspector-hint player-map-note">
                        <Unlock :size="12" /> Mapas e camadas são somente leitura para jogadores. A câmera fica salva neste dispositivo.
                    </p>
                    <p v-if="imageErrors[Object.keys(imageErrors).find((key) => key.startsWith('object-')) ?? '']" class="field-error" role="alert">
                        Uma imagem de token autorizada não pôde ser carregada.
                    </p>
                </aside>
            </div>
        </section>
    </AppLayout>
</template>
