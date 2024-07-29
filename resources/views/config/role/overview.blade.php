@extends('layouts.datalist.datatable.overview')
@section('htmlTitle', 'Role Overview')
@section('table_title', 'Roles')

@section('header')
    @vite('resources/ts/config/roles/overview.ts')
@endsection

{{--| table |--}}
@section('table')
    <x-datalist.datatable.table id="overview-table" url="{{route('data.config.roles.overview.datatable')}}">

        <x-datalist.datatable.header column="id" visible="false">
            <input type="hidden" name="id" value="[value]">
        </x-datalist.datatable.header>

        <x-datalist.datatable.header column="name" display="Name"/>

        <x-datalist.datatable.header column="description" display="Description"/>

        @if($user->hasPermission('config.role.permissions'))
            <x-datalist.datatable.header column="buttons">
                <div class="flex flex-col justify-center">
                    <x-elements.buttons.button onclick="openPermissionModal(this.closest('tr'))">edit permissions</x-elements.buttons.button>
                </div>
            </x-datalist.datatable.header>
        @endif

    </x-datalist.datatable.table>
@endsection

{{--| modals |--}}
@section('modals')
    @if($user->hasPermission('config.role.permissions'))
        <x-modal.modal id="role_permission_modal">
            <div class="flex flex-col justify-center">
                <h4 class="title text-center">Permissions</h4>
                <x-datalist.datatable.table id="permission-table" url="{{route('data.config.roles.permissions.datatable', ['role_id' => 'role_id'])}}" dynamic="true" history="false">

                    <x-datalist.datatable.header column="id" visible="false">
                        <input type="hidden" name="id" value="[value]">
                    </x-datalist.datatable.header>

                    <x-datalist.datatable.header column="has_permission">
                        <x-form.input.checkbox name="has_permission" onclick="togglePermission(this.closest(`tr`), this.value)"/>
                    </x-datalist.datatable.header>

                    <x-datalist.datatable.header column="name" display="Permission"/>

                    <x-datalist.datatable.header column="description" display="Description"/>

                    <x-datalist.datatable.header column="group" display="Group"/>

                </x-datalist.datatable.table>
            </div>
        </x-modal.modal>
    @endif
@endsection


