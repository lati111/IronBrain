export function initShortenedDescriptions() {
    (<any>window).expandShortenedDescription = expandShortenedDescription;
    (<any>window).collapseShortenedDescription = collapseShortenedDescription;
}

export function cardlistDescriptionSetter(card: HTMLDivElement, description: string) {
    const maxChars = 80;

    if (description === null) {
        description = '';
    }

    if (description.length <= maxChars) {
        card.querySelector('#shortened-description-container')?.classList.add('hidden')
        card.querySelector('#full-description')?.classList.remove('hidden')
    } else {
        card.querySelector('#expand-btn')?.classList.add('hidden');
    }

    const lastSpacePos = maxChars + description.substring(maxChars, description.length - maxChars).indexOf(' ');
    card.querySelector('#shortened-description')!.textContent = description.substring(0, lastSpacePos);
    card.querySelector('#full-description')!.textContent = description;
}

function expandShortenedDescription(container: HTMLElement) {
    container.querySelector('#shortened-description-container')?.classList.add('hidden')
    container.querySelector('#full-description-container')?.classList.remove('hidden')
}

function collapseShortenedDescription(container: HTMLElement) {
    container.querySelector('#shortened-description-container')?.classList.remove('hidden')
    container.querySelector('#full-description-container')?.classList.add('hidden')
}


