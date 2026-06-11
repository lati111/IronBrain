<div id="armory-cardlist-template" class="card rounded shadow border gray-border p-3 flex flex-col cursor-pointer w-44"
     onclick="openItemModal(this)">
    <input type="hidden" name="item_id">
    <input type="hidden" name="item_type">
    <input type="hidden" name="user_uuid">
    <input type="hidden" name="forma">
    <input type="hidden" name="potato">
    <input type="hidden" name="built">
    <input type="hidden" name="fashioned">
    <input type="hidden" name="exilus">
    <input type="hidden" name="riven">
    <input type="hidden" name="school">
    <input type="hidden" name="shards">

    {{--| Main row: item display + property indicators |--}}
    <div class="flex flex-row gap-1 flex-1">

        {{--| Item display (greyed out when unowned) |--}}
        <div class="flex flex-col items-center gap-1 flex-1 min-w-0"
             data-add-class-if-true-name="unowned" data-class-to-add="opacity-40">
            <img data-name="icon" data-alt-name="name"
                 class="w-24 h-24 object-contain mt-1 mx-auto">
            <span class="text-xs text-center leading-tight font-medium mt-1 break-words w-full"
                  data-name="name"></span>
        </div>

        {{--| Property indicators (owned only) |--}}
        <div class="flex flex-col gap-1 items-center justify-center flex-shrink-0 hidden"
             data-show-if-true-name="owned">

            {{-- Built + Exilus --}}
            <div class="flex flex-row gap-1 items-center">
                <img src="{{asset('img/modules/arsenal/icon/built.png')}}" alt="Built" title="Built"
                     class="mini-icon monochrome active hidden"
                     data-show-if-true-name="built">
                <img src="{{asset('img/modules/arsenal/icon/exilus.png')}}" alt="Exilus Adapter" title="Exilus Adapter"
                     class="mini-icon monochrome active hidden"
                     data-show-if-true-name="exilus">
            </div>

            {{-- Fashioned + Focus School --}}
            <div class="flex flex-row gap-1 items-center">
                <img src="{{asset('img/modules/arsenal/icon/fashioned.png')}}" alt="Fashion Frame" title="Fashion Frame"
                     class="mini-icon monochrome active hidden"
                     data-show-if-true-name="fashioned">
                <img data-name="school_icon" alt="Focus School" title="Focus School"
                     class="mini-icon monochrome active hidden"
                     data-show-if-true-name="has_school">
            </div>

            {{-- Potato --}}
            <img src="{{asset('img/modules/arsenal/icon/potato.png')}}" alt="Orokin Reactor / Catalyst" title="Orokin Reactor / Catalyst"
                 class="mini-icon active hidden"
                 data-show-if-true-name="potato">

            {{-- Forma --}}
            <div class="flex items-center leading-none gap-0.5 hidden" data-show-if-true-name="has_forma">
                <img src="{{asset('img/modules/arsenal/icon/forma.png')}}" alt="Forma" class="mini-icon active">
                <span class="arsenal-indicator-count" data-name="forma"></span>
            </div>

            {{-- Shards --}}
            <div class="flex items-center leading-none gap-0.5 hidden" data-show-if-true-name="has_shards">
                <img src="{{asset('img/modules/arsenal/icon/shard.png')}}" alt="Shards" class="mini-icon active">
                <span class="arsenal-indicator-count" data-name="shards"></span>
            </div>

            {{-- Riven --}}
            <img src="{{asset('img/modules/arsenal/icon/riven.png')}}" alt="Riven" title="Riven"
                 class="mini-icon active hidden"
                 data-show-if-true-name="riven">

        </div>
    </div>
</div>
