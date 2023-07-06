@extends('layouts.datalist.cardlist.main')

@section('htmlTitle', 'Home')
@section('title', 'Home')

@section('url', route('pksanc.overview.cardlist'))

@section('before')
    <div class="absolute right-72 top-0">
        <a href="#" class="interactive">Deposit Pokemon</a>
    </div>
@endsection
