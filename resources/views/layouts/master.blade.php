<!doctype html>
<html lang="en" data-bs-theme="auto">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Lati111">
    <title>IronBrain | @yield('htmlTitle')</title>

    @vite(['resources/css/app.css','resources/js/app.js'])
    <link href="{{ asset('css/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    @yield('header')
</head>

<body>
    {{-- <div id="toast-default" class="absolute flex items-center w-full max-w-xs p-4 text-gray-500 bg-white rounded-lg shadow dark:text-gray-400 dark:bg-gray-800" role="alert">
        <div class="ml-3 text-sm font-normal">Set yourself free.</div>
        <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex h-8 w-8 dark:text-gray-500 dark:hover:text-white dark:bg-gray-800 dark:hover:bg-gray-700" data-dismiss-target="#toast-default" aria-label="Close">
            <span class="sr-only">Close</span>
            <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
        </button>
    </div> --}}

    @if ($message = Session::get('error'))
        <div class="error text-center"><strong>{{$message}}</strong></div>
    @endif
    @if ($message = Session::get('message'))
        <div class="error text-center"><strong>{{$message}}</strong></div>
    @endif
    @foreach ($errors->all(':message') as $error)
        <div class="error text-center"><strong>{{$error}}</strong></div>
    @endforeach


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

<script src="{{ asset('js/bootstrap/bootstrap.bundle.js') }}"></script>
@yield('script')

</html>
