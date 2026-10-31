import {toggleMenu} from "./menu";
import {post} from "./state";

/** Moving a job to the front of its queue, from the menu opened by right clicking it */

/**
 * Opens the menu over a job, from where it is moved to the front of its queue so it is worked on next
 * @param anchor The element the menu hovers over
 * @param status A line describing the job
 * @param jobUuids The jobs that can be prioritised, labelled by the given labels when there are several
 * @param labels The label of each job, when there are several to choose from
 */
export function openPrioritizeMenu(anchor: HTMLElement, status: string, jobUuids: string[], labels: string[] = []): void {
    toggleMenu(anchor, status, (option) => {
        jobUuids.forEach((uuid, index) => {
            option(labels[index] !== undefined ? `Prioritize ${labels[index]}` : 'Prioritize', () => prioritizeWork(uuid));
        });
    });
}

async function prioritizeWork(uuid: string): Promise<void> {
    await post(`/work/${uuid}/prioritize`, new FormData(), false);
}
