@props([
    'module',
    'update' => null,
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
            {{--| links to the changelog of this module, grayed out once its latest update was seen |--}}
            @if($update !== null)
                <a href="{{$update['url']}}" class="text-sm {{$update['seen'] ? 'cancel_interactive' : 'interactive'}}" title="View the changelog"
                   dusk="changelog-{{$module->code}}">
                    Updated {{\App\Service\TimeService::time_elapsed_string($update['date'])}}
                </a>
            @else
                <small class="text-body-secondary">
                    Updated {{\App\Service\TimeService::time_elapsed_string($module->updated_at)}}
                </small>
            @endif
        </div>
    </div>
</div>
