import {BoostTarget, postOnce} from "../shared";
import {renderRooms} from "./rooms";
import {renderSidebar} from "./sidebar";

/** The restaurant's floor as the server sends it, and the state of the manage restaurant screen */

export interface Room {
    uuid: string;
    name: string;
    background_image: string | null;
}

export interface Table {
    uuid: string;
    name: string;
    capacity: number;
    /** Null while the table is not placed in a room */
    room_uuid: string | null;
    /** How many customers are seated at the table */
    seated: number;
}

export interface Customer {
    uuid: string;
    name: string;
    description: string | null;
    /** Both null while the customer is not seated */
    table_uuid: string | null;
    seat: number | null;
}

export interface Employee {
    uuid: string;
    name: string;
    icon: string | null;
    cleaning_speed: number;
    prep_speed: number;
    /** The player controlling the employee during service, if any */
    player_uuid: string | null;
    player_name: string | null;
}

/** A user invited to play, who controls the employees assigned to them during service */
export interface Player {
    uuid: string;
    username: string;
}

/** A boost activated during service on any of its targets */
export interface BoostType {
    uuid: string;
    name: string;
    targets: BoostTarget[];
    /** Added to the speed of what it is on while it lasts */
    speed_bonus: number;
    /** Minutes of prep or cooking done straight away */
    minutes_off: number;
    /** Added to the quality of a meal it is on */
    quality_bonus: number;
    /** Minutes added to how long a meal it is on stays fresh once served */
    fresh_for_bonus: number;
    /** How many minutes the speed bonus lasts */
    duration: number;
}

export interface Floor {
    rooms: Room[];
    tables: Table[];
    customers: Customer[];
    employees: Employee[];
    players: Player[];
    boosts: BoostType[];
}

/** The sidebar lists tables, customers, employees, players and boosts */
export type SidebarTab = 'table' | 'customer' | 'employee' | 'player' | 'boost';

/** The tabs whose entries are edited in a modal of their own, and deleted from there */
export type EditableTab = Exclude<SidebarTab, 'player'>;

export const TAB_LABELS: Record<SidebarTab, string> = {
    table:    'Table',
    customer: 'Customer',
    employee: 'Employee',
    player:   'Player',
    boost:    'Boost',
};

export const state = {
    apiUrl: '',
    editUrl: '',
    floor: {rooms: [], tables: [], customers: [], employees: [], players: [], boosts: []} as Floor,
    activeTab: 'table' as SidebarTab,
    /** The uuid of what is open in the edit modal of each tab, or null when the modal is for something new */
    editing: {table: null, customer: null, employee: null, boost: null} as Record<EditableTab, string | null>,
};

export function setFloor(data: Floor): void {
    state.floor = data;
    renderSidebar();
    renderRooms();
}

/**
 * Posts to a floor endpoint and shows the updated floor it returns
 * @return Whether the request succeeded
 */
export async function postFloor(url: string, formData: FormData = new FormData(), announce: boolean = true): Promise<boolean> {
    try {
        const response = await postOnce(url, formData);
        if (!response?.ok) return false;
        if (announce) response.announce();

        setFloor(response.data.floor);
        return true;
    } catch {
        // The error has already been shown to the user
        return false;
    }
}

//| Lookups

export function getRoom(uuid: string | null): Room | null {
    return uuid !== null ? state.floor.rooms.find((room) => room.uuid === uuid) ?? null : null;
}

export function getTable(uuid: string): Table | null {
    return state.floor.tables.find((table) => table.uuid === uuid) ?? null;
}

export function seatsLabel(seats: number): string {
    return seats === 1 ? '1 seat' : `${seats} seats`;
}
