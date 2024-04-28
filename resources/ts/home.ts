import {DataCardlist} from "./components/datalists/DataCardlist";

/**
 * Initializes the home module overview
 */
async function init() {
    const overview = new DataCardlist('module-cardlist')
    await overview.init();
    console.log(overview)
}

(<any>window).init = init;
