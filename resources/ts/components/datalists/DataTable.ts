import {Datatable as LaravelDataTable} from "@lati111/laravel_datatables/src/Templates/Datatable/Datatable";
import {getData as fetchGet, postData as fetchPost} from "../../main";

/** @inheritDoc */

export class DataTable extends LaravelDataTable {
    /** @inheritDoc */
    async fetchData(url: string): Promise<any> {
        const response = await fetchGet(url);
        return response.data;
    }

    /** @inheritDoc */
    async postData(url: string, parameters: FormData): Promise<any> {
        const response = await fetchPost(url, parameters);
        response?.announce();
        return response?.data;
    }
}
