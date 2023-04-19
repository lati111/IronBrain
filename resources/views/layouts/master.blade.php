<!doctype html>
<html lang="en" data-bs-theme="auto">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Lati111">
    <title>IronBrain | @yield('htmlTitle')</title>

    @vite('resources/css/app.css')
    <link href="{{ asset('css/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    @yield('header')
</head>

<body>
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
                    <li><a href="/" class="nav-link interactive px-2 link-secondary">Overview</a></li>
                    {{-- <li><a href="#" class="nav-link interactive px-2 link-body-emphasis">Inventory</a></li>
                    <li><a href="#" class="nav-link interactive px-2 link-body-emphasis">Customers</a></li>
                    <li><a href="#" class="nav-link interactive px-2 link-body-emphasis">Products</a></li> --}}
                </ul>

                {{--| search bar |--}}
                {{-- <form class="col-12 col-lg-auto mb-3 mb-lg-0 me-lg-3" role="search">
                    <input type="search" class="form-control" placeholder="Search..." aria-label="Search">
                </form> --}}

                {{--| account icon |--}}
                <div class="dropdown flex justify-center">
                    <a href="#" class="flex items-center link-dark text-decoration-none dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://cdn.discordapp.com/avatars/233948668447817728/b41993097639122be18c0f2ada8bac79?size=1024" alt="" width="32" height="32" class="rounded-circle">
                    </a>

                    {{--| account dropdown |--}}
                    <ul class="dropdown-menu text-small shadow">
                        {{-- <li><a class="dropdown-item" href="#">New project...</a></li>
                        <li><a class="dropdown-item" href="#">Settings</a></li>
                        <li><a class="dropdown-item" href="#">Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li> --}}
                        <li><a class="dropdown-item" href="#">Sign out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>
</body>

<script src="{{ asset('js/bootstrap/bootstrap.bundle.min.js') }}"></script>
@yield('script')

</html>
