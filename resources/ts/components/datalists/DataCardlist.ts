import {DataCardList as LaravelDataCardList} from "@lati111/laravel_datatables/src/Templates/Cardlist/DataCardList";
import {getData as fetchGet, postData as fetchPost} from "../../main";
import {IronbrainError} from "../../Exceptions/IronbrainError";
import {openModal} from "../modal";
import {DataSelect} from "./DataSelect";
import {DatalistConstructionError} from "@lati111/laravel_datatables/src/Exceptions/DatalistConstructionError";

/** @inheritDoc */

export class DataCardlist extends LaravelDataCardList {
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

    public openFilterModal (modalId:string) {
        const filterSelect = (document.querySelector('#'+modalId)?.querySelector('select[name="filter"]') ?? null) as HTMLSelectElement|null;
        if (filterSelect === null) {
            throw new IronbrainError('Filter select was not found on filter modal #'+modalId);
        }

        const operatorSelect = (document.querySelector('#'+modalId)?.querySelector('select[name="operator"]') ?? null) as HTMLSelectElement|null;
        if (operatorSelect === null) {
            throw new IronbrainError('Operator select was not found on filter modal #'+modalId);
        }

        let elements = filterSelect.options;
        for(let i = 0; i < elements.length; i++){
            elements[i].selected = false;
        }

        filterSelect.style.order = '1';

        operatorSelect.classList.add('hidden');
        operatorSelect.style.order = '2'

        const valueDataSelectContainer = (document.querySelector('#'+modalId)?.querySelector('.dataselect-container') ?? null) as HTMLDivElement|null;
        if (valueDataSelectContainer !== null) {
            valueDataSelectContainer!.classList.add('hidden');
            valueDataSelectContainer!.style.order = '3'
        }

        const valueSelects = document.querySelector('#'+modalId)?.querySelectorAll('.filter-value-select') as NodeListOf<HTMLElement>;
        for (const valueSelect of valueSelects) {
            valueSelect.classList.add('hidden');
            valueSelect.style.order = '3'
        }

        openModal(modalId);
    }

    protected filterSetup(): void {
        super.filterSetup();

        //setup filter form dataselect
        if (this.filterForm !== null) {
            const valueSelect = this.filterForm.querySelector('.dataselect-container [name="dataselect"]');
            if (valueSelect !== null) {
                if (valueSelect.id === null) {
                    valueSelect.id = this.dataproviderID + '-value-dataselect';
                }

                this.valueDataSelect = new DataSelect(valueSelect.id)
                this.valueDataSelectContainer = this.filterForm.querySelector('.dataselect-container');
            }
        }

        //setup datafilterselects
        const datafilterselects = document.querySelectorAll(`.${this.dataproviderID}-data-filter-select[data-filter-operator]`);
        for (const datafilterselect of datafilterselects) {
            this.filterDataSelects[datafilterselect.id] = new DataSelect(datafilterselect.id);
        }
    }
}
