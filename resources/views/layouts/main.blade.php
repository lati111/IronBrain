<!doctype html>
<html lang="en" data-bs-theme="auto">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="Lati111">
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>@yield('htmlTitle') | IronBrain</title>

        @vite(['resources/css/app.css', 'resources/ts/app.ts', 'resources/ts/main.ts'])
        @yield('header')
    </head>

    <body onload="toastInit(); @yield('onload_functions')" class="relative">
        {{--| toasts |--}}
        @include('layouts.parts.toasts')

        {{--| modals |--}}
        @yield('modals')
        <div id="load-indicator" tabindex="-1"
             class="modal forced fixed top-0 left-0 right-0 hidden p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full" style="z-index: 75">
            <div class="relative" style="min-width:28em">
                <div class="relative bg-white rounded-lg shadow">
                    <div class="p-6 mx-6 space-y-6 flex flex-col justify-center items-center">
                        <p>Loading...</p>
                        <div class="spinner flex justify-center py-6 h-20 w-20">
                            <img src="{{asset('img/icons/loading.svg')}}" class="animate-spin w-full h-full" alt="busy loading">
                        </div>
                    </div>
                </div>
            </div>
        </div>


        {{--| header |--}}
        @include('layouts.parts.header')

        {{--| body |--}}
        <main class="relative flex justify-center items-center">
            <div id="container">
                @yield('content')
            </div>
        </main>
    </body>
</html>
