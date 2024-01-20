import { getData } from '../ajax.js';

export class DataProvider {
    private readonly dataProviderID:string;
    private readonly dataHandler:Function;
    private readonly element!: Element;
    private readonly dataUrl!: string;

    private searchbar: HTMLInputElement | null = null;
    private searchterm:string|null = null;
    private searchfields:string|null = null;

    private pagination: HTMLElement | null = null;
    private perpage: number = 10;
    private page: number = 1;

    private filterlist: HTMLInputElement | null = null;
    private filters:Array<{'filter':string|null, 'operator':string|null, 'value':string|null}> = [];

    constructor(dataproviderID:string, dataHandler:Function) {
        this.dataProviderID = dataproviderID;
        this.dataHandler = dataHandler;

        const dataProviderElement:Element|null = document.querySelector('#'+dataproviderID);
        if (dataProviderElement === null) {
            new Error('Could not find dataprovider with ID '+dataproviderID)
            return;
        }

        this.element = dataProviderElement;
        const dataUrl = this.element.getAttribute('data-content-url');
        if (dataUrl === null) {
            new Error('Could not find attribute data-content-url on dataprovider with ID '+dataproviderID)
            return;
        }

        this.dataUrl = dataUrl;
        this.loadStoredUrlData(false)

        window.addEventListener("popstate", this.loadStoredUrlData.bind(this, true));

        this.dataHandler(this);
    }

    //| searchbar
    private initSearchbar(storedUrl:URL) {
        this.searchbar = document.querySelector('#'+this.dataProviderID+'-searchbar');
        if (this.searchbar !== null) {
            const searchfields:string = this.searchbar.getAttribute("data-searchfields") ?? ''
            this.searchfields = searchfields;

            this.searchterm = storedUrl.searchParams.get('search') ?? '';
            this.searchbar.value = this.searchterm;

            this.searchbar.addEventListener('keydown', this.applySearchbar.bind(this));

            let confirmkey:HTMLElement|null = this.searchbar.closest('div');
            if (confirmkey !== null) {
                confirmkey = confirmkey.querySelector('button');
            }

            if (confirmkey === null) {
                console.error('Malformed searchbar with ID '+this.dataProviderID)
                return;
            }

            confirmkey.addEventListener('click', this.applySearchbar.bind(this));
        }
    }

    public applySearchbar(e:Event|KeyboardEvent) {
        if (this.searchbar === null) {
            return;
        }

        if (e instanceof KeyboardEvent) {
            if (e.key !== undefined && e.key !== 'Enter') {
                return;
            }
        }

        const searchterm = this.searchbar.value;

        if (searchterm !== this.searchterm) {
            this.page = 1;
            this.searchterm = searchterm;
            this.update();
        }
    }

    //| pagination
    public async loadPagination() {
        if (this.pagination !== null) {
            this.pagination.innerHTML = '';
            if (this.page <= 0) {
                this.page = 1;
            }

            let totalPages = 0;
            let dataUrl = this.pagination.getAttribute('data-url');
            if (dataUrl !== null) {
                dataUrl += '?' + this.getUrl().toString().split('?');
                const data = await getData(dataUrl);
                totalPages = parseInt(data);
            }

            // show left arrow
            if (this.page !== 1) {
                const prevpage:number = this.page - 1;
                this.pagination.append(this.createPaginationNode('<', prevpage));
            } else {
                this.pagination.append(this.createEmptyPaginationNode());
            }

            if (this.page > 8) { // show skip to first page
                this.pagination.append(this.createPaginationNode('1', 1))
                this.pagination.append(this.createPaginationDivider());
            } else { // show 2 more pages left
                for(let i = this.page - 7; i < this.page - 5; i++) {
                    if (i > 0) {
                        this.pagination.append(this.createPaginationNode(i.toString(), i))
                    } else {
                        this.pagination.append(this.createEmptyPaginationNode());
                    }
                }
            }

            // show pages left of current page
            for(let i = this.page - 5; i < this.page; i++) {
                if (i > 0) {
                    this.pagination.append(this.createPaginationNode(i.toString(), i))
                } else {
                    this.pagination.append(this.createEmptyPaginationNode());
                }
            }

            // show current page
            this.pagination.append(this.createPaginationNode(this.page.toString(), this.page, true))

            // show pages right of current page
            for(let i = this.page + 1; i < this.page + 6; i++) {
                if (i <= totalPages) {
                    this.pagination.append(this.createPaginationNode(i.toString(), i))
                } else {
                    this.pagination.append(this.createEmptyPaginationNode());
                }
            }

            if (this.page < totalPages - 7) { // show skip to final pages
                this.pagination.append(this.createPaginationDivider());
                this.pagination.append(this.createPaginationNode(totalPages.toString(), totalPages))
            } else { // show right 2 numbers
                for(let i = totalPages - 1; i < totalPages + 1; i++) {
                    if (i < totalPages - 2 && i > this.page) {
                        this.pagination.append(this.createPaginationNode(i.toString(), i))
                    } else {
                        this.pagination.append(this.createEmptyPaginationNode());
                    }
                }
            }

            // show right arrow
            if (this.page !== totalPages) {
                const nextpage:number = this.page + 1;
                this.pagination.append(this.createPaginationNode('>', nextpage));
            } else {
                this.pagination.append(this.createEmptyPaginationNode());
            }
        }
    }

    public applyPagination(page:number) {
        this.page = page;
        this.update();
    }

    private createPaginationNode(text:string, page:number, current:boolean = false) {
        const element = document.createElement('button');
        element.classList.add('w-4');
        element.classList.add('interactive');
        element.classList.add('text-center');
        element.textContent = text;
        if (current) {
            element.classList.add('active');
        }

        element.addEventListener('click', this.applyPagination.bind(this, page))
        return element;
    }

    private createPaginationDivider() {
        const element = document.createElement('span');
        element.classList.add('w-4');
        element.classList.add('primary-red');
        element.textContent = '...';
        return element;
    }

    private createEmptyPaginationNode() {
        const element = document.createElement('button');
        element.classList.add('w-4');
        return element;
    }

    //| filters
    private initFilters() {
        this.filterlist = document.querySelector('#'+this.dataProviderID+'-filterlist');
        if (this.filterlist !== null) {
            const filtercontainer = this.filterlist.querySelector('.filter-container')!;
            const observer = new MutationObserver(this.applyFilters.bind(this));
            observer.observe(filtercontainer, {characterData: false, childList: true, attributes: false});
        }
    }

    public applyFilters() {
        if (this.filterlist === null) {
            return;
        }

        this.filters = [];
        const filterElements = this.filterlist.querySelectorAll('.filter');
        for (const filterElement of filterElements) {
            const filter = {
                'filter': filterElement.getAttribute('data-filter'),
                'operator': filterElement.getAttribute('data-operator'),
                'value': filterElement.getAttribute('data-value'),
            };

            this.filters.push(filter);
        }

        this.update();
    }

    //| utility
    public getElement(): Element {
        return this.element;
    }

    public getUrl():URL {
        const url = new URL(this.dataUrl);
        if (this.pagination !== null) {
            url.searchParams.set('page', ''+this.page);
            url.searchParams.set('perpage', ''+this.perpage);
        }

        if (this.searchbar !== null) {
            url.searchParams.set('search', ''+this.searchterm);
            //url.searchParams.set('searchfields', ''+this.searchfields);
        }

        if (this.filterlist !== null) {
            url.searchParams.set('filters', JSON.stringify(this.filters));
        }

        return url;
    }

    private update(reload:boolean = false) {
        if (reload === false) {
            let pageUrl = new URL(window.location.href);
            const params:string = this.getUrl().toString().split('?')[1];
            pageUrl.searchParams.set(this.dataProviderID, params);
            window.history.pushState({urlPath:pageUrl.toString()}, '', pageUrl.toString());
        }

        this.dataHandler(this);
    }

    public loadStoredUrlData(reload:boolean = false) {
        let pageUrl = new URL(window.location.href);
        let storedParams:string = pageUrl.searchParams.get(this.dataProviderID) ?? '';
        if (storedParams !== '') {
            storedParams = '?' + storedParams;
        }

        const storedUrl:URL = new URL(this.dataUrl + storedParams);
        this.initSearchbar(storedUrl);

        this.pagination = document.querySelector('#'+this.dataProviderID+'-pagination');
        const perpage = storedUrl.searchParams.get('perpage') ?? '6';
        this.perpage = parseInt(perpage);
        const page = storedUrl.searchParams.get('page') ?? '1';
        this.page = parseInt(page);

        this.loadPagination();
        this.initFilters();

        if (reload === true) {
            this.update(reload);
        }
    }

}
