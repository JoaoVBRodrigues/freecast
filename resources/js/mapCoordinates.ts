export interface Point {
    x: number;
    y: number;
}

export interface Camera {
    x: number;
    y: number;
    scale: number;
}

const MIN_SCALE = 0.08;
const MAX_SCALE = 5;

export function screenToWorld(point: Point, camera: Camera): Point {
    return {
        x: (point.x - camera.x) / camera.scale,
        y: (point.y - camera.y) / camera.scale,
    };
}

export function worldToScreen(point: Point, camera: Camera): Point {
    return {
        x: point.x * camera.scale + camera.x,
        y: point.y * camera.scale + camera.y,
    };
}

export function zoomAtPoint(camera: Camera, point: Point, factor: number): Camera {
    const worldPoint = screenToWorld(point, camera);
    const scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, camera.scale * factor));

    return {
        scale,
        x: point.x - worldPoint.x * scale,
        y: point.y - worldPoint.y * scale,
    };
}

export function snapWorldPointToGrid(point: Point, cellSize: number): Point {
    return {
        x: Math.round(point.x / cellSize) * cellSize,
        y: Math.round(point.y / cellSize) * cellSize,
    };
}
