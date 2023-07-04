<div class="cardlist flex justify-center"
    data-content-url="{{$dataUrl}}"
    @isset($cardSize) data-card-size="{{$cardSize}}" @endisset
/>
    <div class="card flex flex-col justify-center shadow-sm p-3">
        <div class="flex justify-center items-center">
            <div class="flex flex-col justify-center items-center h-full w-8 my-2 gap-2">
                <img src="{{asset("img/project/pksanc/pokeball/poke-ball.png")}}" alt="pokeball icon" title="sprite" class="h-8">
                <img src="{{asset("img/project/pksanc/icon/gender/male.png")}}" alt="male gender icon" title="male" class="h-6">
                <img src="{{asset("img/project/pksanc/icon/tera/poison.png")}}" alt="poison tera type icon" title="poison tera type" class="h-8">
                <img src="{{asset("img/project/pksanc/icon/alpha.png")}}" alt="alpha icon" title="alpha" class="h-6">
                <img src="{{asset("img/project/pksanc/icon/shiny.png")}}" alt="shiny icon" title="shiny" class="h-6">
            </div>

            <div class="flex items-center px-3 h-full"><div class="divider"></div></div>

            <div class="flex flex-col justify-center gap-0 w-24">
                <span class="text-center" title="species">Alolan Bulbasaur</span>
                <img src="{{asset("img/project/pksanc/pokemon/venusaur_default.png")}}" alt="bulbasaur sprite" title="sprite" class="w-24 h-24">
                <div class="flex flex-col items-center">
                    <span class="text-center" title="name">Bulb</span>
                </div>
            </div>

            <div class="flex items-center px-3 h-full"><div class="divider"></div></div>

            <div class="flex flex-col items-center gap-3">
                <span class="text-center" title="level">Level 24</span>
                <span class="text-center" title="nature">Adamant</span>
                <span class="text-center" title="ability">Overgrow</span>
                <img src="{{asset("img/project/pksanc/icon/type/dark_full.png")}}" alt="dark hidden power type" title="hidden power" class="w-28 h-6">
            </div>

            <div class="flex items-center px-3 h-full"><div class="divider"></div></div>

            <div>
                <div class="flex flex-col items-center gap-3">
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-center" title="save file">Fire Type Only</span>
                        <span class="text-center" title="caught game">Scarlet Compass</span>
                    </div>
                    <span class="text-center" title="caught location">Area 4 South</span>
                    <div class="flex flex-row items-center gap-1">
                        <span class="text-center" title="trainer">Juliana</span>
                        <img src="{{asset("img/project/pksanc/icon/gender/female.png")}}" alt="female gender icon" title="female" class="h-5">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
