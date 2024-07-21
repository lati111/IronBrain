@extends('layouts.datalist.datatable.overview')
@section('htmlTitle', 'User Overview')
@section('table_title', 'Users')

@section('header')
    @vite('resources/ts/config/users/overview.ts')
@endsection

{{--| table |--}}
@section('table')
    <x-datalist.datatable.table id="overview-table" data_url="{{route('data.config.users.overview.datatable')}}">

        <x-datalist.datatable.header column="uuid" visible="false">
            <input type="hidden" name="uuid" value="[value]">
        </x-datalist.datatable.header>

        <x-datalist.datatable.header column="profile_picture" width="8">
            <img src="/img/profile/[value]" class="h-12 p-1">
        </x-datalist.datatable.header>

        <x-datalist.datatable.header column="username" display="Username" sortable="true"/>

        <x-datalist.datatable.header column="email" display="Email Address" sortable="true"/>

        <x-datalist.datatable.header column="role" display="Role" sortable="true"/>

        <x-datalist.datatable.header column="buttons">
            <div class="flex flex-col justify-center">
                @if($user->hasPermission('config.user.role'))
                    <x-elements.buttons.button onclick="openChangeRoleModal(this.closest('tr'))">change role</x-elements.buttons.button>
                @endif
            </div>
        </x-datalist.datatable.header>

    </x-datalist.datatable.table>
@endsection

{{--| modals |--}}
@section('modals')
    @if($user->hasPermission('config.user.role'))
        @component('components.modal.modal')
            @slot('id')role_modal @endslot
            @slot('name')role_id @endslot
            @slot('body')
                <div class="flex flex-col justify-center">
                    <h4 class="title text-center">Change Role</h4>
                    <x-datalist.dataselect.dataselect id="role-selector" name="role" identifier="id" label="name"
                                                      url="{{route('data.config.roles.dataselect')}}"/>
                </div>
            @endslot
            @slot('buttons')
                <x-elements.buttons.button cls="cancel_interactive" onclick="closeModal('role_modal')">Cancel</x-elements.buttons.button>
                <x-elements.buttons.button onclick="changeRole()">Change</x-elements.buttons.button>
            @endslot
        @endcomponent
    @endif
@endsection
