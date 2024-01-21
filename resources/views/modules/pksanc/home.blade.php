@extends('layouts.datalist.cardlist.main')

@section('htmlTitle', 'PKSanc Box')
@section('title', 'Box')

@section('css')
    @vite(['resources/css/project/pksanc/box.css'])
@stop

@section('url', route('pksanc.overview.cardlist'))

@section('before')
    <div id="flex justify-center">
        <div id="top-bar-container" class="relative">
            <a href="{{route('pksanc.deposit.show')}}" class="interactive absolute right-0 top-0">Deposit Pokemon</a>
        </div>
    </div>
    <div class="pb-4"></div>
@endsection
