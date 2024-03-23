<!doctype html>
<html lang="en" data-bs-theme="auto">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Lati111">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('htmlTitle') | IronBrain</title>

    @vite(['resources/css/app.css','resources/ts/app.ts'])
    @yield('header')
</head>

<body onload="toastInit(); @yield('onloadFunction')" class="relative">
    <div id="toasts" class="absolute flex flex-col gap-2 top-3 left-3 w-64" dusk="toasts">
        @if ($error = Session::get('error'))
            @component('components.toast')
                @slot('id') error-toast-0 @endslot
                @slot('text') {{$error}} @endslot
                @slot('class') error-toast @endslot
            @endcomponent
        @endif
        @foreach ($errors->all() as $error)
            @component('components.toast')
                @slot('id') error-toast-{{$loop->index + 1}} @endslot
                @slot('text') {{$error}} @endslot
                @slot('class') error-toast @endslot
            @endcomponent
        @endforeach

        @if ($message = Session::get('message'))
            @component('components.toast')
                @slot('id') message-toast-0 @endslot
                @slot('text') {{$message}} @endslot
                @slot('class') message-toast @endslot
            @endcomponent
        @endif
    </div>

    {{--| header |--}}
    <header class="p-3 mb-3 border-bottom bg-body-tertiary">
        <div class="flex justify-center items-center w-full">
            <div class="relative flex justify-start items-center flex-wrap w-full max-w-screen-lg gap-4">
                {{--| logo |--}}
                <a href="/"
                   class="flex items-center link-body-emphasis text-decoration-none">
                    <img src="{{ asset('img/logo_cropped.svg') }}" alt="IronBrain" class="" width="160"
                         height="40" role="img" aria-label="IronBrain" id="logo"/>
                </a>

                {{--| nav items |--}}
                <ul class="flex justify-center items-center gap-2" dusk="nav">
                    @isset($navCollection)
                        @foreach ($navCollection as $nav)
                            <li dusk="{{$nav->name}}">
                                @if(count($nav->Submenu) > 0)
                                    <x-lists.dropdown.main id="nav-{{$nav->uuid}}" title="{{$nav->name}}" cls="@if (Route::is($submenu->route))active @endif">
                                        @foreach ($nav->Submenu as $submenu)
                                            <x-lists.dropdown.option href="{{route($submenu->route)}}">{{$submenu->name}}</x-lists.dropdown.option>
                                        @endforeach
                                    </x-lists.dropdown.main>
                                @elseif ($nav->route !== null)
                                    <x-lists.dropdown.false-dropdown title="{{$nav->name}}" cls="@if (Route::is($submenu->route))active @endif"/>
                                @endif
                            </li>
                        @endforeach
                    @endisset
                </ul>

                {{--| authentication |--}}
                <div class="absolute right-0 flex justify-center items-center gap-2" dusk="auth_header">
                    @if(Auth::user() !== null)
                        @component('components.lists.dropdown.main')
                            @slot('id', 'account-dropdown')
                            @slot('title')
                                <img src="{{asset(sprintf('img/profile/%s/pfp.svg', Auth::user()->uuid))}}" alt="pfp" width="32" height="32" class="rounded-circle">
                            @endslot
                            @slot('slot')
                                <x-lists.dropdown.static-option>{{Auth::user()->name}}</x-lists.dropdown.static-option>
                                <li><hr class="dropdown-divider"></li>
                                <x-lists.dropdown.option href="{{route('auth.logout')}}">Sign out</x-lists.dropdown.option>
                            @endslot
                        @endcomponent
                    @else
                        {{--| login / sign up |--}}
                        <div class="flex justify-center gap-3">
                            <a href="{{route('auth.login.show')}}" class="interactive" dusk="login">Log In</a>
                            <a href="{{route('auth.signup.show')}}" class="interactive" dusk="signup">Sign Up</a>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </header>

    <main class="relative">
        @yield('content')
    </main>
</body>

@vite(['resources/ts/main.ts'])
@yield('script')

</html>
