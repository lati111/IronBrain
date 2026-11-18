@extends('layouts.main')

@section('htmlTitle', 'Changelog')
@section('title', 'Changelog')

@section('content')
    <section class="pt-3 pb-5 container max-w-3xl mx-auto flex flex-col gap-4" dusk="changelog">
        <h1 class="title text-center">Changelog{{$subject !== null ? ' - '.$subject : ''}}</h1>
        @if($subject !== null || request()->filled('module'))
            <a href="{{route('changelog.show')}}" class="interactive text-center text-sm" dusk="changelog-all">Show the full changelog</a>
        @endif

        @forelse($releases as $release)
            {{--| only the latest version is expanded by default |--}}
            <details class="rounded shadow border gray-border p-3" dusk="release-{{$release['version'] ?? ''}}" @if($loop->first) open @endif>
                <summary class="cursor-pointer select-none flex justify-between items-center gap-2">
                    <span class="title text-xl">Version {{$release['version'] ?? 'unknown'}}</span>
                    @if(!empty($release['date']))
                        <small class="text-body-secondary">{{$release['date']}}</small>
                    @endif
                </summary>

                <div class="flex flex-col gap-3 pt-3">
                    {{--| already limited to what the user may see, grouped per module and submodule ('' for none) |--}}
                    @foreach($release['modules'] as $module => $groups)
                        <div dusk="module-{{\Illuminate\Support\Str::slug($module)}}">
                            <h3 class="title text-lg border-b gray-border pb-1">{{$module}}</h3>
                            @foreach($groups as $submodule => $submoduleChanges)
                                @if($submodule !== '')
                                    <h4 class="title text-base pt-2">{{$submodule}}</h4>
                                @endif
                                <ul class="list-disc pl-6 pt-1">
                                    @foreach($submoduleChanges as $change)
                                        <li>{{$change}}</li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </details>
        @empty
            <p class="text-center text-body-secondary">No changes have been logged yet.</p>
        @endforelse
    </section>
@stop
