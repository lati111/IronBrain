@extends('layouts.main')
@section('htmlTitle', isset($role) ? 'Modify Role' : 'New Role')

@section('content')
    <div class="flex justify-center">
        <div>
            {{--| title |--}}
            <div class="flex flex-row justify-center mb-3">
                <h3 class="title">{{ isset($role) ? 'Modify Role' : 'New Role' }}</h3>
            </div>

            {{--| form |--}}
            <div class="flex flex-row justify-center mb-3" dusk="form">
                <form id="form" method="POST" action="{{route('config.role.save')}}">
                    @csrf

                    @isset($role)
                        <input type="hidden" name="id" value="{{$role->id}}">
                    @endisset

                    <div class="flex flex-col justify-center align-items-center gap-4">
                        {{--| name field |--}}
                        <x-form.input-wrapper label_text="Name">
                            <input type="text" name="name" class="largeInput underlined" placeholder="Name" maxlength="28"
                                   value="{{old('name', $role->name ?? '')}}" dusk="name_input" required/>
                        </x-form.input-wrapper>

                        {{--| description field |--}}
                        <x-form.input-wrapper label_text="Description">
                            <textarea name="description" class="largeInput underlined h-24" placeholder="Description"
                                      dusk="description_input" required>{{old('description', $role->description ?? '')}}</textarea>
                        </x-form.input-wrapper>
                    </div>

                    {{--| submitter |--}}
                    <div class="flex flex-col mt-3">
                        <button type="submit" class="interactive" dusk="submit">{{ isset($role) ? 'Save Role' : 'Add Role' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
