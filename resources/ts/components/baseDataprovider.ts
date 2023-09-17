export function getUrl(dataproviderID:string, dataproviderElement:Element): string {
    let url = new URL(dataproviderElement.getAttribute('data-content-url')!);
    url = applySearchbar(dataproviderID, url);

    return url.toString();
}

function applySearchbar(dataproviderID:string, url: URL): URL {
    const searchbar:HTMLInputElement|null = document.querySelector('#'+dataproviderID+'-searchbar');
    if (searchbar !== null) {
        const searchfields:string = searchbar.getAttribute("data-searchfields") ?? "";
        const searchterm:string = searchbar.value;

        url.searchParams.set("searchfields", searchfields);
        url.searchParams.set("searchterm", searchterm);
    }

    return url;
}
