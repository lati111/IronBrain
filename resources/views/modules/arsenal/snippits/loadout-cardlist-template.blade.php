<div id="loadout-cardlist-template" class="card rounded shadow border gray-border p-4">
    <input type="hidden" name="uuid">

    {{--| Loadout name header |--}}
    <div class="text-center font-medium pb-2 mb-3 border-b border-gray-200">
        <span data-name="loadout_name"></span>
    </div>

    {{--| Equipment slots row |--}}
    <div class="flex justify-center">

        {{--| Warframe |--}}
        <div class="flex flex-col items-center px-3">
            <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Warframe</span>
            <img data-name="warframe_icon" data-alt-name="warframe_name" class="w-16 h-16 object-contain mt-1">
            <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="warframe_name"></span>
        </div>

        {{--| Divider |--}}
        <div class="flex items-center px-2">
            <div class="divider"></div>
        </div>

        {{--| Weapons |--}}
        <div class="flex gap-1">

            {{--| Primary |--}}
            <div class="flex flex-col items-center px-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Primary</span>
                <div class="hidden mt-1" data-show-if-true-name="has_primary">
                    <img data-name="primary_icon" data-alt-name="primary_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_primary">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="primary_name"></span>
            </div>

            {{--| Secondary |--}}
            <div class="flex flex-col items-center px-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Secondary</span>
                <div class="hidden mt-1" data-show-if-true-name="has_secondary">
                    <img data-name="secondary_icon" data-alt-name="secondary_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_secondary">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="secondary_name"></span>
            </div>

            {{--| Melee |--}}
            <div class="flex flex-col items-center px-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Melee</span>
                <div class="hidden mt-1" data-show-if-true-name="has_melee">
                    <img data-name="melee_icon" data-alt-name="melee_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_melee">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="melee_name"></span>
            </div>

        </div>

        {{--| Divider |--}}
        <div class="flex items-center px-2">
            <div class="divider"></div>
        </div>

        {{--| Companion |--}}
        <div class="flex gap-1">

            {{--| Companion |--}}
            <div class="flex flex-col items-center px-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Companion</span>
                <div class="hidden mt-1" data-show-if-true-name="has_companion">
                    <img data-name="companion_icon" data-alt-name="companion_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_companion">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="companion_name"></span>
            </div>

            {{--| Companion Weapon |--}}
            <div class="flex flex-col items-center px-2">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Comp. Weapon</span>
                <div class="hidden mt-1" data-show-if-true-name="has_companion_weapon">
                    <img data-name="companion_weapon_icon" data-alt-name="companion_weapon_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_companion_weapon">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="companion_weapon_name"></span>
            </div>

        </div>

    </div>
</div>
