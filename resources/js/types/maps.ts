export interface RoomMapSummary {
    id: number;
    title: string;
    revision: number;
    is_active?: boolean;
    width: number;
    height: number;
    background: string | null;
    updated_at: string;
}

export interface MapAsset {
    id: number;
    name: string;
    kind: 'background' | 'token';
    width: number;
    height: number;
    folder_id: number | null;
    is_master_only: boolean;
    url: string;
}

export interface MapFolder {
    id: number;
    parent_id: number | null;
    name: string;
    is_master_only: boolean;
}

export interface MapObject {
    id: number;
    revision: number;
    layer_id: number;
    asset_id: number;
    name: string;
    x: number;
    y: number;
    width: number;
    height: number;
    rotation: number;
    z_index: number;
    visible: boolean;
    locked: boolean;
    assigned_user_id: number | null;
    can_move: boolean;
    asset_name: string;
    url: string | null;
}

export interface MapLayer {
    id: number;
    revision: number;
    type: 'background' | 'objects' | 'tokens' | 'gm';
    name: string;
    sort_order: number;
    visible: boolean;
    locked: boolean;
    objects: MapObject[];
}
