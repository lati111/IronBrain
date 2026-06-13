@extends('layouts.main')
@section('htmlTitle', isset($role) ? 'Modify Role' : 'New Role')

@section('content')
<div>
    <h1>{{ isset($role) ? 'Modify Role' : 'New Role' }}</h1>
</div>
@endsection
