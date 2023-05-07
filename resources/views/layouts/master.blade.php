<!doctype html>
<html lang="en" data-bs-theme="auto">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Lati111">
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>IronBrain | @yield('htmlTitle')</title>

    @vite(['resources/css/app.css','resources/ts/app.ts'])
    <link href="{{ asset('css/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    @yield('header')
</head>

<body onload="init(); @yield('onloadFunction')" class="relative">
    <div class="absolute flex flex-col gap-2 top-3 left-3 w-64">
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
        <div class="container">
            <div class="flex items-center justify-center flex-wrapjustify-content-lg-start">
                {{--| logo |--}}
                <a href="/"
                    class="flex items-center link-body-emphasis text-decoration-none">
                    <img src="{{ asset('img/logo_cropped.svg') }}" alt="IronBrain" class="" width="160"
                        height="40" role="img" aria-label="IronBrain" id="logo"/>
                </a>

                {{--| nav items |--}}
                <ul class="nav col-12 col-lg-auto me-lg-auto justify-content-center mb-md-0 ml-2">
                    {{-- <li><a href="/" class="nav-link interactive px-2 link-secondary">Overview</a></li> --}}
                    @foreach ($navCollection as $nav)
                        <li>
                            @if(count($nav->Submenu) > 0)
                                <span class="nav-link interactive px-2 link-secondary dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                />{{$nav->name}}</span>

                                <ul class="dropdown-menu interactive px-2 link-secondary">
                                    @foreach ($nav->Submenu as $submenu)
                                        <li><a class="dropdown-item" href="{{route($submenu->route)}}">{{$submenu->name}}</a></li>
                                    @endforeach
                                </ul>
                            @else
                                <a href="{{route($nav->route)}}" class="nav-link interactive px-2 link-secondary">{{$nav->name}}</a>
                            @endif

                        </li>
                    @endforeach
                </ul>

                {{--| authentication |--}}
                <div class="dropdown flex justify-center">
                    @if(isset($user))
                        {{--| account icon |--}}
                            <a href="#" class="flex items-center link-dark text-decoration-none dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="{{asset(sprintf('img/profile/%s/pfp.svg', $user->uuid))}}" alt="pfp" width="32" height="32" class="rounded-circle">
                        </a>

                        {{--| account dropdown |--}}
                        <ul class="dropdown-menu text-small shadow">
                            <li><span class="dropdown-item pointer-events-none">{{$user->name}}</span></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="{{route('auth.logout')}}">Sign out</a></li>
                        </ul>
                    @else
                        {{--| login / sign up |--}}
                        <div class="flex justify-center gap-3">
                            <a href="{{route('auth.login.show')}}" class="interactive">Log In</a>
                            <a href="{{route('auth.signup.show')}}" class="interactive">Sign Up</a>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>
</body>

@vite(['resources/ts/main.ts'])
<script src="{{ asset('js/bootstrap/bootstrap.bundle.js') }}"></script>
@yield('script')

</html>
