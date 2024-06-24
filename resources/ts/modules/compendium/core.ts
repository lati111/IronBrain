import {getData} from "../../ajax";
import {Statblock} from "./data/Statblock";

async function test() {
    const data = await getData('/compendium/data');
    const statblock = new Statblock(data);
}

(<any>window).test = test;
