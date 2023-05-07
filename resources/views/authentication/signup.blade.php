@extends('layouts.master')

@section('htmlTitle', 'Add Submenu')

@section('content')

{{--| pagination form |--}}
<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                Create New Account
            </h3>
        </div>

        {{--| form |--}}
        <div class="flex flex-row justify-center mb-3">
            <form id="form" action="{{route('auth.signup.save')}}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="flex flex-col justify-center gap-4">
                    {{--| username field |--}}
                    <input type="text" name="name" class="largeInput underlined" placeholder="Name"
                        @if(old('name') !== null) value="{{old('name')}}" @endif
                        required
                    />

                    {{--| email field |--}}
                    <input type="email" name="email" class="largeInput underlined" placeholder="Email Adress"
                        @if(old('email') !== null) value="{{old('email')}}" @endif
                        required
                    />

                    {{--| password field |--}}
                    <input type="password" name="password" class="largeInput underlined" placeholder="Password"
                        @if(old('password') !== null) value="{{old('password')}}" @endif
                        required
                    />

                    {{--| repeat password field |--}}
                    <input type="password" name="repeat_password" class="largeInput underlined" placeholder="Repeat Password"
                        @if(old('repeat_password') !== null) value="{{old('repeat_password')}}" @endif
                        required
                    />
                </div>

                {{--| submitter |--}}
                <div class="flex flex-col mt-3">
                    <input type="submit" class="interactive" value="Create Account">
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
