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
                {{-- <th scope="col" class="text-center">Permission</th> --}}
                <th scope="col" class="text-center">Route</th>
                <th scope="col" class="text-center">
                    <a href="{{route('config.projects.new')}}" class="interactive no-underline">Add Project</a>
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach ($projects as $project)
            <tr class="">
                <td>
                    <img
                        style="height: 48px"
                        src="{{asset("img/project/sprites/".$project["thumbnail"])}}"
                        alt="{{$project["thumbnail"]}}"
                    />
                </td>
                <td class="text-center">{{$project["name"]}}</td>
                <td class="text-center">{{$project["description"]}}</td>
                {{-- <td class="text-center">{{$project["permission"]}}</td> --}}
                <td class="text-center">{{$project["route"]}}</td>
                <td>
                    <div class="text-center interactive">edit</div>
                    <div class="text-center interactive">delete</div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>


</div>


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
@stop
