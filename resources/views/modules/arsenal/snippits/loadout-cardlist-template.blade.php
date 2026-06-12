<div id="loadout-cardlist-template" class="card rounded shadow border gray-border p-4" data-loadout-item>
    <input type="hidden" name="uuid">

    {{--| Equipment slots |--}}
    <div class="flex items-start justify-center gap-2">

        {{--| Warframe, loadout name  |--}}
        <div class="flex flex-col gap-1">
            <x-arsenal.loadout-equipment category="warframe" text="Warframe"/>

            <div class="text-center font-medium pb-2 mb-3 border-b border-gray-200">
                <span data-name="loadout_name" onclick="startRenameLoadout(this)"
                      class="cursor-text hover:underline decoration-dotted underline-offset-2">
                </span>
            </div>
        </div>

        <div class="self-stretch flex items-center px-1">
            <div class="divider"></div>
        </div>

        {{--| Primary / secondary |--}}
        <div class="flex flex-col gap-1">
            <x-arsenal.loadout-equipment category="primary" text="Primary"/>
            <x-arsenal.loadout-equipment category="secondary" text="Secondary"/>
        </div>

        {{--| Melee / companion |--}}
        <div class="flex flex-col gap-1">
            <x-arsenal.loadout-equipment category="melee" text="Melee"/>
            <x-arsenal.loadout-equipment category="companion" text="Companion"/>
        </div>

    </div>
</div>
