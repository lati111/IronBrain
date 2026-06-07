<div id="foundry-cardlist-template" class="card relative rounded shadow border gray-border px-3 py-2 w-80" data-blueprint-item>
    <input type="hidden" name="blueprint_id">
    <input type="hidden" name="blueprint_type">
    <input type="hidden" name="completion_pct">
    <input type="hidden" name="already_owned">

    {{--| Already-owned indicator |--}}
    <div class="absolute top-1.5 left-1.5 w-4 h-4 bg-green-500 rounded-full items-center justify-center hidden"
         data-show-if-true-name="already_owned">
        <svg viewBox="0 0 10 10" width="8" height="8" fill="none" stroke="white" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M1.5 5l2.5 2.5 4.5-4"/>
        </svg>
    </div>

    {{--| Blueprint icon + name beside component slots, all on one row |--}}
    <div class="flex items-start gap-3">

        {{--| Blueprint icon + name |--}}
        <div class="flex flex-col items-center flex-shrink-0 w-16">
            <div class="relative w-12 h-12" data-blueprint-icon-wrapper>
                <img data-name="icon" data-alt-name="name" class="w-12 h-12 object-contain">
            </div>
            <span class="text-xs text-center leading-tight mt-0.5 break-words w-full" data-name="name"></span>
        </div>

        {{--| Component slots (populated by foundry.ts) |--}}
        <div class="flex flex-wrap gap-1 flex-1 items-start" data-component-slots></div>

    </div>

    {{--| Completion bar |--}}
    <div class="mt-1.5">
        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-blue-400 rounded-full transition-all" data-completion-bar style="width: 0%"></div>
        </div>
        <div class="text-xs text-gray-400 text-right leading-none mt-0.5" data-completion-label></div>
    </div>
</div>
