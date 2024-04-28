<div id="{{$id}}" class="pagination flex justify-center items-center h-8 gap-2"
     data-count-url="{{$url}}"
     data-previous-page-button-ID="{{$id}}-pagination-prev"
     data-next-page-button-ID="{{$id}}-pagination-next"
     data-content-ID="{{$id}}-pagination-content"
     data-perpage-selector-ID="{{$id}}-perpage-selector"
     data-page-cls="interactive text-center w-8 h-8 px-1"
     data-page-number-cls="btn"
     data-pages-in-pagination="{{$pages_in_pagination ?? 7}}">

    <button id="{{$id}}-pagination-prev" class="interactive btn px-2 py-1"><</button>
    <div id="{{$id}}-pagination-content" class="flex justify-center items-center gap-2"></div>
    <button id="{{$id}}-pagination-next" class="interactive btn px-2 py-1 ">></button>
</div>
