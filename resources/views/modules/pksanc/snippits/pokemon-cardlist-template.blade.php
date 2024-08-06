<div id="pokemon-cardlist-template" class="flex justify-center gap-4">
    <div class="card flex justify-center rounded shadow border gray-border p-3">
        {{--| icon list |--}}
        <div class="card-body flex justify-center items-center">
            <div class="flex flex-col justify-center items-center h-full w-8 my-2 gap-2">
                <img data-name="prev-pokeball_sprite" data-alt-name="prev-pokeball"
                     data-attribute-name="prev-pokeball_name" data-settable-attribute="title" class="h-8">
                <img data-name="prev-gender_sprite" data-alt-name="prev-gender" data-attribute-name="prev-gender"
                     data-settable-attribute="title" class="h-6">
                <img data-name="prev-tera_sprite" data-alt-name="prev-tera_type" data-attribute-name="prev-tera_type"
                     data-settable-attribute="title" class="h-8">
                <img src="{{asset('img/modules/pksanc/icon/shiny.png')}}" alt="shiny icon" title="Shiny"
                     data-show-if-true-name="prev-is_shiny" class="hidden h-6">
                <img src="{{asset('img/modules/pksanc/icon/alpha.png')}}" alt="alpha icon" title="Alpha"
                     data-show-if-true-name="prev-is_alpha" class="hidden h-6">
                <img src="{{asset('img/modules/pksanc/icon/dyna.png')}}" alt="gigantamax icon"
                     title="Gigantamaxable" data-show-if-true-name="prev-can_gigantamax" class="hidden h-6">
                <img src="{{asset('img/modules/pksanc/icon/n_sparkle.png')}}" alt="N sparkle icon"
                     title="N sparkle" data-show-if-true-name="prev-has_n_sparkle" class="hidden h-6">
            </div>

            <div class="flex items-center px-3 h-full">
                <div class="divider"></div>
            </div>

            {{--| pokemon info list |--}}
            <div class="pksanc-minimal-block flex flex-col justify-center gap-0 w-24">
                <span class="text-center" title="species" data-name="prev-nickname"></span>
                <img data-name="prev-sprite" data-alt-name="prev-pokemon" title="sprite"
                     class="w-24 h-24">
                <div class="flex flex-col items-center">
                    <span class="text-center" data-name="prev-pokemon_name" title="name"></span>
                </div>
            </div>

            <div class="flex items-center px-3 h-full">
                <div class="divider"></div>
            </div>

            {{--| stat list |--}}
            <div class="pksanc-minimal-block flex flex-col items-center gap-2">
                <span class="text-center" title="level">
                    <span>Level </span>
                    <span data-name="prev-level"></span>
                </span>
                <span class="text-center" title="nature" data-name="prev-nature"></span>
                <span class="text-center" title="ability" data-name="prev-ability"></span>
                <img data-name="prev-hidden_power_sprite" data-alt-name="prev-hidden_power_type"
                     data-attribute-name="prev-hidden_power_type" data-settable-attribute="title"
                     class="w-28 h-6">
            </div>

            <div class="flex items-center px-3 h-full">
                <div class="divider"></div>
            </div>

            {{--| origin list |--}}
            <div class="pksanc-minimal-block flex flex-col items-center gap-2 w-32">
                <div class="flex flex-col items-center gap-0">
                    <span class="text-center" title="save file" data-name="prev-save_name"></span>
                    <span class="text-center" title="caught game" data-name="prev-game_name"></span>
                </div>
                <span class="text-center" title="caught location" data-name="prev-met_location"></span>
                <div class="flex flex-row items-center gap-1">
                    <span class="text-center" title="trainer" data-name="prev-trainer_name"></span>
                    <img data-name="prev-trainer_gender_sprite" data--alt-name="prev-trainer_gender"
                         data-attribute-name="prev-trainer_gender" data-settable-attribute="prev-trainer_gender"
                         class="h-5">
                </div>
            </div>
        </div>
    </div>

    <img src="{{asset('img/icons/chevron-right-double.svg')}}" alt="Transforms into" class="w-8">

    <div class="card flex justify-center rounded shadow border gray-border p-3">
        {{--| icon list |--}}
        <div class="card-body flex justify-center items-center">
            <div class="flex flex-col justify-center items-center h-full w-8 my-2 gap-2">
                <img data-name="pokeball_sprite" data-alt-name="pokeball"
                     data-attribute-name="pokeball_name" data-settable-attribute="title" class="h-8">
                <img data-name="gender_sprite" data-alt-name="gender" data-attribute-name="gender"
                     data-settable-attribute="title" class="h-6">
                <img data-name="tera_sprite" data-alt-name="tera_type" data-attribute-name="tera_type"
                     data-settable-attribute="title" class="h-8">
                <img src="{{asset('img/modules/pksanc/icon/shiny.png')}}" alt="shiny icon" title="Shiny"
                     data-show-if-true-name="is_shiny" class="hidden h-6">
                <img src="{{asset('img/modules/pksanc/icon/alpha.png')}}" alt="alpha icon" title="Alpha"
                     data-show-if-true-name="is_alpha" class="hidden h-6">
                <img src="{{asset('img/modules/pksanc/icon/dyna.png')}}" alt="gigantamax icon"
                     title="Gigantamaxable" data-show-if-true-name="can_gigantamax" class="hidden h-6">
                <img src="{{asset('img/modules/pksanc/icon/n_sparkle.png')}}" alt="N sparkle icon"
                     title="N sparkle" data-show-if-true-name="has_n_sparkle" class="hidden h-6">
            </div>

            <div class="flex items-center px-3 h-full">
                <div class="divider"></div>
            </div>

            {{--| pokemon info list |--}}
            <div class="pksanc-minimal-block flex flex-col justify-center gap-0 w-24">
                <span class="text-center" title="species" data-name="nickname"></span>
                <img data-name="sprite" data-alt-name="pokemon" title="sprite"
                     class="w-24 h-24">
                <div class="flex flex-col items-center">
                    <span class="text-center" data-name="pokemon_name" title="name"></span>
                </div>
            </div>

            <div class="flex items-center px-3 h-full">
                <div class="divider"></div>
            </div>

            {{--| stat list |--}}
            <div class="pksanc-minimal-block flex flex-col items-center gap-2">
                            <span class="text-center" title="level">
                                <span>Level </span>
                                <span data-name="level"></span>
                            </span>
                <span class="text-center" title="nature" data-name="nature"></span>
                <span class="text-center" title="ability" data-name="ability"></span>
                <img data-name="hidden_power_sprite" data-alt-name="hidden_power_type"
                     data-attribute-name="hidden_power_type" data-settable-attribute="title"
                     class="w-28 h-6">
            </div>

            <div class="flex items-center px-3 h-full">
                <div class="divider"></div>
            </div>

            {{--| origin list |--}}
            <div class="pksanc-minimal-block flex flex-col items-center gap-2 w-32">
                <div class="flex flex-col items-center gap-0">
                    <span class="text-center" title="save file" data-name="save_name"></span>
                    <span class="text-center" title="caught game" data-name="game_name"></span>
                </div>
                <span class="text-center" title="caught location" data-name="met_location"></span>
                <div class="flex flex-row items-center gap-1">
                    <span class="text-center" title="trainer" data-name="trainer_name"></span>
                    <img data-name="trainer_gender_sprite" data--alt-name="trainer_gender"
                         data-attribute-name="trainer_gender" data-settable-attribute="trainer_gender"
                         class="h-5">
                </div>
            </div>
        </div>
    </div>
</div>
