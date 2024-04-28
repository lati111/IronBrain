import {DataCardlist} from "../../components/datalists/DataCardlist";

/**
 * Initializes the pksanc box page
 */
async function init() {
    const overview = new DataCardlist('pokemon-cardlist')
    await overview.init();
    console.log(overview)
}

(<any>window).init = init;
