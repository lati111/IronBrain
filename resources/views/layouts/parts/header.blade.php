{{--| header |--}}
<header class="p-3 mb-3 border-bottom bg-body-tertiary">
    <div></div>

    {{--| logo |--}}
    <a href="/" class="col-start-2"> <img src="{{ asset('img/logo_cropped.svg') }}" alt="IronBrain" role="img" aria-label="IronBrain" id="logo"/> </a>

    {{--| nav items |--}}
    <nav class="col-start-3 flex items-center gap-2">
        @isset($modules)
            @foreach ($modules as $module)
                <div dusk="{{$module->name}}">
                    @if(count($module->submodules) > 0)
                        <x-lists.dropdown.main id="nav-{{$module->uuid}}" title="{{$module->name}}" cls="@if (Route::is($submenu->route))active @endif">
                            @foreach ($module->submodules as $submenu)
                                <x-lists.dropdown.option href="{{route($submenu->route)}}">{{$submenu->name}}</x-lists.dropdown.option>
                            @endforeach
                        </x-lists.dropdown.main>
                    @elseif ($module->route !== null)
                        <x-lists.dropdown.false-dropdown href="{{route($module->route)}}" title="{{$module->name}}" cls="@if (Route::is($submenu->route))active @endif"/>
                    @endif
                </div>
            @endforeach
        @endisset
    </nav>

    {{--| authentication |--}}
    <div class="col-start-4 flex justify-end items-center gap-2" dusk="auth_header">
        @if(Auth::user() !== null)
            @component('components.lists.dropdown.main')
                @slot('id', 'account-dropdown')
                @slot('title')
                    <img src="{{asset(sprintf('img/profile/%s/pfp.svg', Auth::user()->uuid))}}" alt="pfp" width="32" height="32" class="rounded-circle">
                @endslot
                @slot('slot')
                    <x-lists.dropdown.static-option>{{Auth::user()->username}}</x-lists.dropdown.static-option>
                    <li><hr class="dropdown-divider"></li>
                    <x-lists.dropdown.option href="{{route('auth.logout')}}">Sign out</x-lists.dropdown.option>
                @endslot
            @endcomponent
        @else
            {{--| login / sign up |--}}
            <div class="flex justify-center gap-3">
                <a href="{{route('auth.login')}}" class="interactive" dusk="login">Log In</a>
                <a href="{{route('auth.signup')}}" class="interactive" dusk="signup">Sign Up</a>
            </div>
        @endif
    </div>
</header>
