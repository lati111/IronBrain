@props([
    'module',
])

{{--| A module or submodule in the home overview |--}}
<div class="card max-w-md flex flex-col items-center gap-2 rounded shadow border gray-border p-3" dusk="module-{{$module->code}}">
    @if($module->thumbnail !== null)
        <img src="{{asset('img/modules/thumbnail/'.$module->thumbnail)}}" alt="{{$module->name}}" class="max-w-sm">
    @endif

    <div class="card-body">
        <h4 class="title text-center">
            <a href="{{route($module->route)}}" class="interactive">
                <span class="text-xl">{{$module->name}}</span>
            </a>
        </h4>

        <p class="card-text text-center">{{$module->description}}</p>

        <div class="flex justify-content-between align-items-center">
            <small class="text-body-secondary" title="Last Updated">
                {{\App\Service\TimeService::time_elapsed_string($module->updated_at)}}
            </small>
        </div>
    </div>
</div>
