{{--| header |--}}
<header class="p-3 mb-3 border-bottom bg-body-tertiary">
    <div class="flex justify-center items-center w-full">
        <div class="relative flex justify-start items-center flex-wrap gap-4">
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
                        {{--| modules without accessible submodules or a route of their own have nothing to show |--}}
                        @if(count($module->submodules) > 0)
                            <li dusk="{{$module->name}}">
                                <x-lists.dropdown.main id="nav-{{$module->code}}" title="{{$module->name}}"
                                                       :cls="$module->submodules->contains(fn ($submodule) => Route::is($submodule->route)) ? 'active' : ''">
                                    @foreach ($module->submodules as $submodule)
                                        <x-lists.dropdown.option href="{{route($submodule->route)}}">{{$submodule->name}}</x-lists.dropdown.option>
                                    @endforeach
                                </x-lists.dropdown.main>
                            </li>
                        @elseif ($module->route !== null)
                            <li dusk="{{$module->name}}">
                                <x-lists.dropdown.false-dropdown href="{{route($module->route)}}" title="{{$module->name}}"
                                                                 :cls="Route::is($module->route) ? 'active' : ''"/>
                            </li>
                        @endif
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
                        <a href="{{route('auth.login')}}" class="interactive" dusk="login">Log In</a>
                        <a href="{{route('auth.signup')}}" class="interactive" dusk="signup">Sign Up</a>
                    </div>
                @endif

            </div>
        </div>
    </div>
</header>
