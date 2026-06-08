<div id="armory-cardlist-template" class="card rounded shadow border gray-border p-3 flex flex-col cursor-pointer"
     onclick="openItemModal(this)">
    <input type="hidden" name="item_id">
    <input type="hidden" name="item_type">
    <input type="hidden" name="user_uuid">
    <input type="hidden" name="forma">
    <input type="hidden" name="potato">
    <input type="hidden" name="built">
    <input type="hidden" name="riven">
    <input type="hidden" name="school">
    <input type="hidden" name="school_abbr">

    {{--| Main row: item display + property indicators |--}}
    <div class="flex flex-row gap-1 flex-1">

        {{--| Property indicators (owned only) |--}}
        <div class="flex flex-col gap-1 items-center justify-center flex-shrink-0 hidden"
             data-show-if-true-name="owned">

            {{-- Built --}}
            <span class="text-[0.6rem] font-bold text-gray-300" data-add-class-if-true-name="built" data-class-to-add="active" title="Built">
                <img src="{{asset('img/modules/arsenal/icon/built.png')}}" alt="Is built" class="mini-icon monochrome">

            </span>

            {{-- Focus School --}}
            <span class="text-[0.6rem] font-bold text-gray-500 uppercase leading-none"
                  data-name="school_abbr"
                  title="Focus School"></span>

        </div>

        {{--| Item display (greyed out when unowned) |--}}
        <div class="flex flex-col items-center gap-1 flex-1 min-w-0" data-add-class-if-true-name="unowned" data-class-to-add="opacity-40">

            <img data-name="icon" data-alt-name="name"
                 class="w-24 h-24 object-contain mt-1 mx-auto">

            <span class="text-xs text-center leading-tight font-medium mt-1 break-words w-full"
                  data-name="name"></span>
        </div>

        {{--| Property indicators (owned only) |--}}
        <div class="flex flex-col gap-1 items-center justify-center flex-shrink-0 hidden"
             data-show-if-true-name="owned">

            {{-- Potato --}}
            <span class="text-[0.6rem] font-bold text-gray-300" data-add-class-if-true-name="potato" data-class-to-add="active" title="Orokin Reactor / Catalyst">
                <img src="{{asset('img/modules/arsenal/icon/potato.png')}}" alt="Orokin Reactor / Catalyst" class="mini-icon">
            </span>

            {{-- Forma --}}
            <div class="flex items-center leading-none gap-0.5">
                <span class="text-[0.45rem] text-gray-400 uppercase tracking-wide leading-none">
                    <img src="{{asset('img/modules/arsenal/icon/forma.png')}}" alt="Forma" class="mini-icon active">
                </span>
                <span class="text-[0.7rem] font-bold text-gray-600 leading-none" data-name="forma"></span>
            </div>

            {{-- Riven --}}
            <span class="text-[0.6rem] font-bold text-gray-300" data-add-class-if-true-name="riven" data-class-to-add="active" title="Riven">
                <img src="{{asset('img/modules/arsenal/icon/riven.png')}}" alt="Has riven" class="mini-icon">
            </span>

        </div>
    </div>
</div>
