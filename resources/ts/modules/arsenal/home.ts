import {DataCardlist} from "../../components/datalists/DataCardlist";

async function init() {
    const overview = new DataCardlist('loadout-cardlist');
    await overview.init();
}

(<any>window).init = init;
