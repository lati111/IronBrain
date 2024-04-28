import {DataSelect as LaravelDataselect} from "@lati111/laravel_datatables/src/Templates/Select/Dataselect";
import {getData as fetchGet, postData as fetchPost} from "../../ajax";

/** @inheritDoc */

export class DataSelect extends LaravelDataselect {
    /** @inheritDoc */
    async fetchData(url: string): Promise<any> {
        const response = await fetchGet(url);
        return response;
    }

    /** @inheritDoc */
    async postData(url: string, parameters: FormData): Promise<any> {
        const response = await fetchPost(url, parameters);
        //response?.announce();
        return response;
    }
}
