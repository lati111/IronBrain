<div id="loadout-cardlist-template" class="card rounded shadow border gray-border p-4" data-loadout-item>
    <input type="hidden" name="uuid">

    {{--| Editable name header |--}}
    <div class="text-center font-medium pb-2 mb-3 border-b border-gray-200">
        <span data-name="loadout_name"
              class="cursor-text hover:underline decoration-dotted underline-offset-2"
              onclick="startRenameLoadout(this)"></span>
    </div>

    {{--| Equipment slots |--}}
    <div class="flex items-start justify-center gap-2">

        {{--| Warframe (large, single) |--}}
        <div class="flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1"
             onclick="openSlotModal(this, 'warframe')">
            <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Warframe</span>
            <div class="hidden mt-1" data-show-if-true-name="has_warframe">
                <img data-name="warframe_icon" data-alt-name="warframe_name" class="w-16 h-16 object-contain">
            </div>
            <div class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_warframe">
                <span class="text-gray-300">—</span>
            </div>
            <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="warframe_name"></span>
        </div>

        <div class="self-stretch flex items-center px-1">
            <div class="divider"></div>
        </div>

        {{--| Primary (top) + Secondary (bottom) |--}}
        <div class="flex flex-col gap-1">

            <div class="flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1"
                 onclick="openSlotModal(this, 'primary')">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Primary</span>
                <div class="hidden mt-1" data-show-if-true-name="has_primary">
                    <img data-name="primary_icon" data-alt-name="primary_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_primary">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="primary_name"></span>
            </div>

            <div class="flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1"
                 onclick="openSlotModal(this, 'secondary')">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Secondary</span>
                <div class="hidden mt-1" data-show-if-true-name="has_secondary">
                    <img data-name="secondary_icon" data-alt-name="secondary_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_secondary">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="secondary_name"></span>
            </div>

        </div>

        {{--| Melee (top) + invisible spacer (bottom, matches slot height) |--}}
        <div class="flex flex-col gap-1">

            <div class="flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1"
                 onclick="openSlotModal(this, 'melee')">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Melee</span>
                <div class="hidden mt-1" data-show-if-true-name="has_melee">
                    <img data-name="melee_icon" data-alt-name="melee_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_melee">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="melee_name"></span>
            </div>

            {{--| Invisible spacer — same structure as a slot to match secondary row height |--}}
            <div class="invisible pointer-events-none flex flex-col items-center px-2 py-1" aria-hidden="true">
                <span class="text-xs font-medium uppercase tracking-wide whitespace-nowrap">&nbsp;</span>
                <div class="w-12 h-12 mt-1"></div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1">&nbsp;</span>
            </div>

        </div>

        <div class="self-stretch flex items-center px-1">
            <div class="divider"></div>
        </div>

        {{--| Companion (top) + Companion Weapon (bottom) |--}}
        <div class="flex flex-col gap-1">

            <div class="flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1"
                 onclick="openSlotModal(this, 'companion')">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">Companion</span>
                <div class="hidden mt-1" data-show-if-true-name="has_companion">
                    <img data-name="companion_icon" data-alt-name="companion_name" class="w-12 h-12 object-contain">
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_companion">
                    <span class="text-gray-300">—</span>
                </div>
                <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="companion_name"></span>
            </div>

            <div class="flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1"
                 onclick="openSlotModal(this, 'companion_weapon')">
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
