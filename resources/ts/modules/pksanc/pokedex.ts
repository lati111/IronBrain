import {DataCardlist} from "../../components/datalists/DataCardlist";

/**
 * Initializes the pksanc box page
 */
async function init() {
    const overview = new DataCardlist('pokedex-cardlist')
    await overview.init();
}

// @ts-ignore
(<any>window).init = init;
