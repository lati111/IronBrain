@extends('layouts.datalist.cardlist.main')

@section('htmlTitle', 'Deposit')
@section('title', 'Deposit')

@section('css')
    @vite(['resources/css/project/pksanc/box.css'])
@stop

@section('url', route('pksanc.staging.cardlist', $importUuid))

@section('before')
<div class="flex flex-col justify-center text-center mb-3">
    <p>The following pokemon will be added to your account.</p>
    <p>Is this correct?</p>
    <div class="flex justify-center gap-5">
        <a href="{{route('pksanc.deposit.stage.cancel', $importUuid)}}" class="cancel_interactive">No</a>
        <a href="{{route('pksanc.deposit.stage.confirm', $importUuid)}}" class="interactive">Yes</a>
    </div>
</div>
@endsection
