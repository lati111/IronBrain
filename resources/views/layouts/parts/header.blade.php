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
                @isset($modules)
                    @foreach ($modules as $module)
                        <li dusk="{{$module->name}}">
                            @if(count($module->submodules) > 0)
                                <x-lists.dropdown.main id="nav-{{$module->uuid}}" title="{{$module->name}}" cls="@if (Route::is($submenu->route))active @endif">
                                    @foreach ($module->submodules as $submenu)
                                        <x-lists.dropdown.option href="{{route($submenu->route)}}">{{$submenu->name}}</x-lists.dropdown.option>
                                    @endforeach
                                </x-lists.dropdown.main>
                            @elseif ($module->route !== null)
                                <x-lists.dropdown.false-dropdown href="{{route($module->route)}}" title="{{$module->name}}" cls="@if (Route::is($submenu->route))active @endif"/>
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
                            <x-lists.dropdown.static-option>{{Auth::user()->username}}</x-lists.dropdown.static-option>
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
