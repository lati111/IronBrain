@extends('layouts.main')

@section('content')
    <div class="flex justify-center">
        <div>
            {{--| title |--}}
            <div class="flex flex-row justify-center mb-3">
                <h3 class="title">
                    @yield('form_title')
                </h3>
            </div>

            {{--| form |--}}
            <div class="flex flex-row justify-center mb-3" dusk="form">
                <form id="form" enctype="multipart/form-data">
                    @csrf

                    <div class="flex flex-col justify-center align-items-center gap-4">
                        @yield('form_content')
                    </div>

                    {{--| submitter |--}}
                    <div class="flex flex-col mt-3">
                        <button type="button" class="interactive" onclick="@yield('submit_method')">@yield('submit_text')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
