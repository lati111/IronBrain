import { getData } from '../ajax.js';
import { DataProvider } from "./baseDataprovider.js";

const divider = '<div class="flex items-center px-3 h-full"><div class="divider"></div></div>';

async function cardlistInit() {
    const cardlistCollection = document.querySelectorAll('div.cardlist');

    cardlistCollection.forEach(async (cardlist) => {
        new DataProvider(cardlist.id, loadCardlist);
    });
}

async function loadCardlist(dataprovider:DataProvider) {
    let cardlist:Element = dataprovider.getElement();
    const data = await getData(dataprovider.getUrl().toString());
    cardlist.innerHTML = "";

    data.forEach((cardData: any[]) => {
        const card:Element = generateCardWrapper();
        const cardBody:Element = card.querySelector('.card-body')!;

        for (let i = 0; i < cardData.length; i++) {
            if (i > 0) {
                cardBody.innerHTML += divider;
            }

            cardBody.innerHTML += cardData[i];
        }

        cardlist.append(card);
    });

    dataprovider.loadPagination();

    if (data.length === 0) {
        cardlist.append(generateEmptyMessage());
    }
}


function generateCardWrapper(): Element {
    const card:Element = document.createElement('div')
    card.classList.add('card');
    card.classList.add('flex');
    card.classList.add('flex-col');
    card.classList.add('justify-center');
    card.classList.add('shadow-sm');
    card.classList.add('p-3');

    const cardBody:Element = document.createElement('div');
    cardBody.classList.add('card-body');
    cardBody.classList.add('flex');
    cardBody.classList.add('justify-center');
    cardBody.classList.add('items-center');
    card.append(cardBody);

    return card;
}

function generateEmptyMessage(): Element {
    const div:Element = document.createElement('div')
    div.classList.add('p-20')
    div.textContent = 'No results found'
    return div
}

(<any>window).cardlistInit = cardlistInit;
