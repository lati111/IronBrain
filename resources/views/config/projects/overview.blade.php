@extends('layouts.master')

@section('htmlTitle', 'Project Overview')

@section('header')
<style>
    .table td {
        height: 72px;
        vertical-align:middle;
    }
</style>
@stop

@section('content')

{{--| pagination form |--}}
<form id="paginationForm" action="{{ route('config.projects.overview') }}" method="GET" enctype="multipart/form-data">
    @if ($page > 1)
        <input type="hidden" name="page" value="{{$page}}">
    @endif
    @if ($perPage != 10)
        <input type="hidden" name="perPage" value="{{$perPage}}">
    @endif
</form>

{{--| table |--}}
<div class="flex justify-center">
    <table class="table table-hover table-striped" style="width: 75%">
        <thead>
            <tr>
                <th scope="col" class="text-center">Thumbnail</th>
                <th scope="col" class="text-center">Name</th>
                <th scope="col" class="text-center">Description</th>
                <th scope="col" class="text-center">Route</th>
                <th scope="col" class="text-center">Nav Order</th>
                <th scope="col" class="text-center">
                    <a href="{{route('config.projects.new')}}" class="interactive no-underline">Add Project</a>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projects as $project)
            <tr class="">
                <td>
                    @if($project["thumbnail"] !== null)
                        <img
                            style="height: 48px"
                            src="{{asset("img/project/thumbnail/".$project["thumbnail"])}}"
                            alt="{{$project["thumbnail"]}}"
                        />
                    @endif
                </td>
                <td class="text-center">{{$project["name"]}}</td>
                <td class="text-center">{{$project["description"]}}</td>
                <td class="text-center">{{$project["route"]}}</td>
                <td class="text-center">{{$project["order"]}}</td>
                <td>
                    <div class="text-center">
                        <a href="{{route("config.projects.modify", $project["id"])}}" class="interactive">edit</a>
                    </div>
                    <div class="text-center">
                        <form action="{{route("config.projects.delete", $project["id"])}}" method="POST">
                            @csrf
                            <span
                                onclick="store_form(this.closest('form'))" class="interactive"
                                data-modal-target="delete_modal" data-modal-toggle="delete_modal"
                                />delete</span>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>


{{--| delete model |--}}
@component('components.delete_model')
    @slot('text') Are you sure you want to delete this project? @endslot
    @slot('confirmFunction') submit_stored_form() @endslot
@endcomponent


{{--| pagination |--}}
<div class="flex justify-center">
    <div class="flex flex-row justify-between">
        @if (ceil($projectCount / $perPage) > 20)
            @if ($page > 5)
                <button class='interactive px-1 w-8' onclick='openPage(1)'>1</button>
                <span class="text-sub text-center px-1 w-8">...</span>
            @elseif ($page === 5)
                <span class='px-1 w-8'></span>
                <button class='interactive px-1 w-8' onclick='openPage(1)'>1</button>
            @else
                <span class='px-1 w-8'></span>
                <span class='px-1 w-8'></span>
            @endif

            @for ($i = $page - 3; $i < $page; $i++)
                @if ($i > 0)
                    <button class='interactive px-1 w-8' onclick='openPage({{$i}})'>{{$i}}</button>
                @else
                    <span class='px-1 w-8'></span>
                @endif
            @endfor

            <button class='interactive active px-1 w-8' onclick='openPage({{$page}})'><b>{{$page}}</b></button>

            @for ($i = $page + 1; $i < $page + 4; $i++)
                @if ($i < ceil($projectCount / $perPage) + 1)
                    <button class='interactive px-1 w-8' onclick='openPage({{$i}})'>{{$i}}</button>
                @else
                    <span class='px-1 w-8'></span>
                @endif
            @endfor

            @if ($page < ceil($projectCount / $perPage) - 4)
                <span class="text-sub text-center px-1 w-8">...</span>
                <button class='interactive px-1 w-8' onclick='openPage({{ceil($projectCount / $perPage)}})'>{{ceil($projectCount / $perPage)}}</button>
            @elseif ($page = (ceil($projectCount / $perPage) - 5))
                <button class='interactive px-1 w-8' onclick='openPage({{ceil($projectCount / $perPage)}})'>{{ceil($projectCount / $perPage)}}</button>
                <span class='px-1 w-8'></span>
            @else
                <span class='px-1 w-8'></span>
                <span class='px-1 w-8'></span>
            @endif
        @else
            @for ($i = 1; $i < ceil($projectCount / $perPage) + 1; $i++)
                @if ($page == $i)
                    <button class='interactive active px-1 w-8' onclick='openPage({{$i}})'><b>{{$i}}</b></button>
                @else
                    <button class='interactive px-1 w-8' onclick='openPage({{$i}})'>{{$i}}</button>
                @endif
            @endfor
        @endif
    </div>
</div>

@stop

@section('script')
<script src="{{ asset('js/pagination.js') }}"></script>
<script src="{{ asset('js/config/project/overview.js') }}"></script>
@stop
