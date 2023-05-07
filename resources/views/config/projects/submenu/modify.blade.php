@extends('layouts.master')

@section('htmlTitle', 'Add Submenu')

@section('content')

{{--| pagination form |--}}
<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                @if(isset($submenu))
                    Modify Submenu
                @else
                    Add New Submenu
                @endif
            </h3>
        </div>

        {{--| form |--}}
        <div class="flex flex-row justify-center mb-3">
            <form id="form" action="{{ route('config.projects.submenu.save', $project_id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @isset($submenu)
                    <input type="hidden" name="id" value="{{$submenu->id}}">
                @endisset
                @if(old('id') !== null)
                    <input type="hidden" name="id" value="{{old('id')}}">
                @endif

                <div class="flex flex-col justify-center gap-4">
                    <div class="flex flex-col gap-4">
                        {{--| name field |--}}
                        <input type="text" name="name" class="largeInput underlined" placeholder="Name"
                            @isset($submenu) value="{{$submenu->name}}" @endisset
                            @if(old('name') !== null) value="{{old('name')}}" @endif
                        />

                        {{--| route field |--}}
                        <input type="text" name="route" class="largeInput underlined" placeholder="Route" required
                            @isset($submenu) value="{{$submenu->route}}" @endisset
                            @if(old('route') !== null) value="{{old('route')}}" @endif
                        />

                        {{--| permission field |--}}
                        <select id="permissionSelect" name="permission_id" class="largeInput underlined py-0">
                            <option value="">No permission</option>
                        </select>
                    </div>

                    {{--| navigation order field |--}}
                    <div id="orderField" class="flex items-center justify-center gap-2 m-0">
                        <label for="default-checkbox" class="ml-2" name="order">Navigation order:</label>
                        <input type="number" name="order" class="w-16 h-4 pr-0 underlined"
                            @isset($submenu) value="{{$submenu->order}}" @endisset
                            @if(old('route') !== null) value="{{old('order')}}" @endif
                            />
                    </div>
                </div>

                {{--| submitter |--}}
                <div class="flex flex-col mt-3">
                    <input type="submit" class="interactive" value="Save Submenu">
                </div>
            </form>
        </div>
    </div>
</div>

@stop

@section('script')
@vite([
    'resources/ts/main.ts',
])
@stop
