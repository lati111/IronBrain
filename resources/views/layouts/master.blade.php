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
    {{--| header |--}}
    @include('layouts.parts.toasts')

    {{--| header |--}}
    @include('layouts.parts.header')

    {{--| body |--}}
    <main class="relative">
        @yield('content')
    </main>
</body>

@vite(['resources/ts/main.ts'])
@yield('script')

</html>
