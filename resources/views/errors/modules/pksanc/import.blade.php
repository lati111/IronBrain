@extends('layouts.error')

@section('errorTitle', 'Deposit failed')

@section('message')
    <p>An error occured during the validating of the csv during depositing.</p>
    <p>Please re-extract the csv and attempt to deposit it again.</p>
    <p>If that does not work, please contact us at support@ironbrain.io and include the error information below</p>

    <br>

    <div class="rounded shadow border gray-border p-3">
        <p>DEPOSIT VALIDATION FAILED</p>
        @foreach($data as $error)
            <p>{{$error}}</p>
        @endforeach
    </div>

    <br>

    <p><a href="{{route('pksanc.deposit.show')}}" class="interactive">Return to deposit</a></p>
@stop
