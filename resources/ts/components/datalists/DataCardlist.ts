import {DataCardList as LaravelDataCardList} from "@lati111/laravel_datatables/src/Templates/Cardlist/DataCardList";
import {getData as fetchGet, postData as fetchPost} from "../../ajax";

/** @inheritDoc */

export class DataCardlist extends LaravelDataCardList {
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
