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

    //| New

    protected columnSetters: {[key: string]: Function} = {}

    /**
     * Set a setter callback to fill in a specific column
     * @param key The column to use this setter on
     * @param callback The callback to use for this setter
     */
    public setColumnSetter(column: string, callback: Function) {
        this.columnSetters[column] = callback;
    }

        protected createItem(data: { [key: string]: any }): HTMLElement {
            const template = this.cardTemplate as HTMLElement;
            const item = template.cloneNode(true) as HTMLElement;
    
            for (const key in data) {
                const value = data[key];

                //Use setter instead
                if (Object.keys(this.columnSetters).includes(key)) {
                    this.columnSetters[key](item, value, data)

                    continue;
                }
    
                //set inputs
                const inputs = item.querySelectorAll(`input[name="${key}"]:not([type="checkbox"]), input[data-name="${key}"]:not([type="checkbox"])`) as NodeListOf<HTMLInputElement>;
                for (let i = 0; i < inputs.length; i++) {
                    const input = inputs[i];
                    if (input !== null) {
                        input.value = value;
                    }
                }
    
                //set checkboxes
                const checkboxes = item.querySelectorAll(`input[name="${key}"][type="checkbox"], input[data-name="${key}"][type="checkbox"]`) as NodeListOf<HTMLInputElement>;
                for (let i = 0; i < checkboxes.length; i++) {
                    const checkbox = checkboxes[i];
                    if (checkbox !== null && (value === true || value === 'true' || value == 1)) {
                        checkbox.checked = true;
                    }
                }
    
    
                //set textarea
                const textareas = item.querySelectorAll(`textarea[name="${key}"], textarea[data-name="${key}"]`) as NodeListOf<HTMLTextAreaElement>;
                for (let i = 0; i < textareas.length; i++) {
                    const textarea = textareas[i];
                    if (textarea !== null) {
                        textarea.textContent = value;
                    }
                }
    
                //set select
                const selects = item.querySelectorAll(`select[name="${key}"], select[data-name="${key}"]`) as NodeListOf<HTMLSelectElement>;
                for (let i = 0; i < selects.length; i++) {
                    const select = selects[i];
                    if (select !== null && value !== null) {
                        const option = select.querySelector(`option[value="${value}"]`) as HTMLOptionElement|null;
                        if (option === null) {
                            throw new DatalistLoadingError(`Option with value "${value}" does not exist on select "${value}"`, this.errorCallback)
                        }
    
                        select.querySelector(`option`)?.removeAttribute('selected');
                        option.setAttribute('selected', 'selected');
                    }
                }
    
                //set spans
                const spans = item.querySelectorAll(`span[name="${key}"], span[data-name="${key}"]`) as NodeListOf<HTMLSpanElement>;
                for (let i = 0; i < spans.length; i++) {
                    const span = spans[i];
                    if (span !== null) {
                        span.textContent = value;
                    }
                }
    
                //set img
                const imgs = item.querySelectorAll(`img[name="${key}"], img[data-name="${key}"]`) as NodeListOf<HTMLImageElement>;
                for (let i = 0; i < imgs.length; i++) {
                    const img = imgs[i];
                    if (img !== null) {
                        img.src = value;
                    }
                }
    
                //set img alt
                const imgAlts = item.querySelectorAll(`img[data-alt-name="${key}"]`) as NodeListOf<HTMLImageElement>;
                for (let i = 0; i < imgAlts.length; i++) {
                    const img = imgAlts[i];
                    if (img !== null) {
                        img.alt = value;
                    }
                }
    
                //show hidden elements
                const hiddenElements = item.querySelectorAll(`[data-show-if-true-name="${key}"]`) as NodeListOf<Element>;
                for (let i = 0; i < hiddenElements.length; i++) {
                    const hidden = hiddenElements[i];
                    if (value == true || value == 1) {
                        hidden.classList.remove('hidden');
                    }
                }
    
                //hide elements
                const unhiddenElements = item.querySelectorAll(`[data-hide-if-true-name="${key}"]`) as NodeListOf<Element>;
                for (let i = 0; i < unhiddenElements.length; i++) {
                    const unhidden = unhiddenElements[i];
                    if (value == true || value == 1) {
                        unhidden.classList.add('hidden');
                    }
                }
    
                //misc data
                const miscAttributes = item.querySelectorAll(`[data-attribute-name="${key}"]`) as NodeListOf<Element>;
                for (let i = 0; i < miscAttributes.length; i++) {
                    const miscAttribute = miscAttributes[i];
                    const attribute = miscAttribute.getAttribute('data-settable-attribute');
                    if (attribute !== null) {
                        miscAttribute.setAttribute(attribute, value);
                    }
                }
    
                const miscClasses = item.querySelectorAll(`[data-add-class-if-true-name="${key}"]`) as NodeListOf<Element>;
                for (let i = 0; i < miscClasses.length; i++) {
                    const miscClass = miscClasses[i];
                    const addableClass = miscClass.getAttribute('data-class-to-add');
                    if (addableClass !== null) {
                        if (value == true || value == 1) {
                            miscClass.classList.add(addableClass)
                        }
                    }
                }
            }
    
            return item;
        }
}
