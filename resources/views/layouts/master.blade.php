<!doctype html>
<html lang="en" data-bs-theme="auto">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Lati111">
    <title>IronBrain | Home</title>

    @vite('resources/css/app.css')
    <link href="{{ asset('css/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
</head>

<body>
    <header class="p-3 mb-3 border-bottom">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-start">
                {{--| logo |--}}
                <a href="/"
                    class="d-flex align-items-center link-body-emphasis text-decoration-none">
                    <img src="{{ asset('img/logo_cropped.svg') }}" alt="IronBrain" class="" width="160"
                        height="40" role="img" aria-label="IronBrain" id="logo"/>
                </a>

                {{--| nav items |--}}
                <ul class="nav col-12 col-lg-auto me-lg-auto justify-content-center mb-md-0">
                    <li><a href="/" class="nav-link px-2 link-secondary">Overview</a></li>
                    <li><a href="#" class="nav-link px-2 link-body-emphasis">Inventory</a></li>
                    <li><a href="#" class="nav-link px-2 link-body-emphasis">Customers</a></li>
                    <li><a href="#" class="nav-link px-2 link-body-emphasis">Products</a></li>
                </ul>

                {{--| search bar |--}}
                {{-- <form class="col-12 col-lg-auto mb-3 mb-lg-0 me-lg-3" role="search">
                    <input type="search" class="form-control" placeholder="Search..." aria-label="Search">
                </form> --}}

                {{--| account icon |--}}
                <div class="flex-shrink-0 dropdown">
                    <a href="#" class="d-block link-dark text-decoration-none dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="" alt="Profile Picture" width="32" height="32" class="rounded-circle">
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
</body>

<script src="{{ asset('js/bootstrap/bootstrap.bundle.min.js') }}"></script>

</html>
