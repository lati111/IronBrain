import {getData} from "../../../../main";
import {postOnce} from "../shared";
import {isMenuOpen} from "./menu";
import {Employee, ServiceState} from "./types";

/** The service state, which the kitchen, the floor and the sidebar are all shown from, and the requests changing it */

let serviceUrl = '';
let state: ServiceState | null = null;

/** Called whenever the service state changes, in the order they were added, so the kitchen and floor show it */
const stateListeners: ((state: ServiceState) => void)[] = [];

/** How often the service is refreshed, in milliseconds, so changes made by others show up */
const REFRESH_INTERVAL = 5000;
/** Counts the changes posted, so a refresh that started before one doesn't overwrite what it returned */
let changeCount = 0;
let dragging = false;

export function getServiceState(): ServiceState | null {
    return state;
}

export function onServiceState(listener: (state: ServiceState) => void): void {
    stateListeners.push(listener);
    if (state !== null) listener(state);
}

function setState(data: ServiceState): void {
    state = data;
    stateListeners.forEach((listener) => listener(data));
}

//| Viewer

/** Whether the one looking is a player, who only controls the employees assigned to them */
export function isPlayer(): boolean {
    return state?.viewer.player ?? false;
}

/** Whether the one looking may move an employee around: anyone but a player, and a player their own employees */
export function canControl(employee: Employee): boolean {
    return !isPlayer() || employee.player_uuid === state!.viewer.user_uuid;
}

//| Loading & refreshing

/** Fetches the service for the first time, from the url all its requests are made to */
export async function loadServiceState(url: string): Promise<void> {
    serviceUrl = url;
    const response = await getData(serviceUrl);
    setState(response.data);
}

/** Keeps the service up to date with changes made by others, refreshing it whenever the page is left alone */
export function startRefreshing(): void {
    document.addEventListener('dragstart', () => dragging = true);
    document.addEventListener('dragend', () => dragging = false);
    document.addEventListener('drop', () => dragging = false);
    setInterval(refreshState, REFRESH_INTERVAL);
}

/**
 * Whether the page is left alone, so refreshing it doesn't get in the way: it is visible, and nothing is being dragged,
 * asked for in a modal, or opened in a menu
 */
export function isIdle(): boolean {
    return document.visibilityState === 'visible' && !dragging && !isMenuOpen()
        && document.querySelector('.modal:not(.hidden)') === null;
}

/** Fetches the service again when the page is left alone, unless something was changed in the meantime */
async function refreshState(): Promise<void> {
    if (!isIdle()) return;

    const count = changeCount;
    const query = new FormData();
    if (state !== null) query.append('version', String(state.version));

    try {
        const response = await getData(serviceUrl, query);
        if (count === changeCount && isIdle() && !response.data.unchanged) {
            setState(response.data);
        }
    } catch {
        // Tried again on the next refresh
    }
}

//| Requests

/**
 * Posts to a service endpoint and shows the updated state it returns
 * @return Whether the request succeeded
 */
export async function post(path: string, formData: FormData | string = new FormData(), announce: boolean = true): Promise<boolean> {
    changeCount++;
    try {
        const response = await postOnce(`${serviceUrl}${path}`, formData);
        if (!response?.ok) return false;
        if (announce) response.announce();

        setState(response.data);
        return true;
    } catch {
        // The error has already been shown to the user
        return false;
    }
}

/** Posts to a service endpoint with the given parameters, such as {area: 'prep'} */
export async function postParameters(path: string, parameters: Record<string, string>, announce: boolean = true): Promise<boolean> {
    const formData = new FormData();
    Object.entries(parameters).forEach(([key, value]) => formData.append(key, value));
    return await post(path, formData, announce);
}
