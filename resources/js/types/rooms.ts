export interface RoomSummary {
    id: number;
    title: string;
    system: string;
    description: string;
    is_public: boolean;
    requires_password: boolean;
    member_count: number;
    owner_name: string;
    is_member: boolean;
    role: string | null;
    is_favorite: boolean;
    is_owner: boolean;
}
