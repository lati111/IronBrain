@extends('layouts.main')

@section('htmlTitle', $restaurant->name)
@section('title', $restaurant->name)

@section('header')
    @vite([
        'resources/css/modules/dnd/restaurant/restaurant.css',
        'resources/ts/modules/dnd/restaurant/manage.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    @php($details = $restaurant->getDetails())
    <div id="manage"
         data-api-url="{{route('api.dnd.restaurant.floor.get', ['restaurant_uuid' => $restaurant->uuid])}}"
         data-edit-url="{{route('api.dnd.restaurant.edit', ['restaurant_uuid' => $restaurant->uuid])}}">

        {{--| Top bar |--}}
        <div class="flex justify-between items-center mb-4">
            <a href="{{route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid])}}" class="interactive w-64">&larr; Management</a>
            <h1 class="title">Manage restaurant</h1>
            <button class="interactive w-64 text-right" onclick="openCreateRoomModal()" dusk="room-create">+ New Room</button>
        </div>

        <div class="flex gap-4 items-start">

            {{--| Sidebar |--}}
            <aside id="inventory-sidebar" class="inventory-sidebar rounded shadow border gray-border">
                <div class="flex items-center border-b">
                    <button class="inventory-sidebar-toggle interactive px-2" onclick="toggleSidebar()" title="Collapse / expand">
                        <span class="inventory-sidebar-toggle-icon">&laquo;</span>
                    </button>
                    <div class="inventory-sidebar-body flex flex-1">
                        <button class="inventory-tab active flex-1 py-2" data-tab="table" onclick="switchTab('table')">Tables</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="customer" onclick="switchTab('customer')">Customers</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="employee" onclick="switchTab('employee')">Employees</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="player" onclick="switchTab('player')">Players</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="boost" onclick="switchTab('boost')">Boosts</button>
                    </div>
                </div>

                <div class="inventory-sidebar-body flex flex-col gap-2 p-2">
                    <input id="inventory-search" type="search" class="underlined w-full" placeholder="Search..." oninput="renderSidebar()">
                    <ul id="inventory-list" class="inventory-list"></ul>
                    <button id="inventory-create-type" class="interactive text-sm" onclick="openCreateModal()">+ New Table</button>
                </div>
            </aside>

            {{--| Main area |--}}
            <div class="flex-1 flex flex-col gap-4 min-w-0">

                {{--| Restaurant details |--}}
                <section class="restaurant-details rounded shadow border gray-border">
                    <form id="restaurant-form" class="flex flex-col gap-3" onsubmit="event.preventDefault(); saveRestaurant()">
                        <div class="flex gap-3 flex-wrap">
                            <x-form.input-wrapper label_text="Name" cls="flex-1 min-w-48">
                                <input type="text" name="name" class="underlined" maxlength="64" value="{{$details['name']}}" required/>
                            </x-form.input-wrapper>
                            <x-form.input-wrapper label_text="Date">
                                <input type="date" name="date" class="underlined" value="{{$details['date']}}" required/>
                            </x-form.input-wrapper>
                            <x-form.input-wrapper label_text="Opening time">
                                <input type="time" name="opening_time" class="underlined" value="{{$details['opening_time']}}" required/>
                            </x-form.input-wrapper>
                            <x-form.input-wrapper label_text="Closing time">
                                <input type="time" name="closing_time" class="underlined" value="{{$details['closing_time']}}" required/>
                            </x-form.input-wrapper>
                        </div>
                        <div class="flex gap-3 items-end flex-wrap">
                            @foreach(['gold' => 'Gold', 'silver' => 'Silver', 'copper' => 'Copper', 'bits' => 'Bits'] as $currency => $currencyLabel)
                                <x-form.input-wrapper label_text="{{$currencyLabel}}" cls="w-24">
                                    <input type="number" name="{{$currency}}" class="underlined" min="0" value="{{$details[$currency]}}" required/>
                                </x-form.input-wrapper>
                            @endforeach
                            <span class="flex-1"></span>
                            <button type="submit" class="interactive px-5 py-2" dusk="restaurant-save">Save details</button>
                        </div>
                    </form>
                </section>

                {{--| Rooms |--}}
                <div id="room-list" class="inventory-storage-list"></div>
                <p id="room-empty" class="hidden text-center text-gray-400 py-6">No rooms yet</p>
            </div>
        </div>
    </div>

    {{--| Drop zone for taking tables out of their room, pinned to the right edge of the viewport while a table is dragged |--}}
    <div id="discard-edge">
        <img src="{{asset('img/icons/x.svg')}}" alt="" class="w-6 h-6">
        <span class="text-sm text-center">Remove from room</span>
    </div>

    {{--| Templates |--}}
    <template id="list-row-template">
        <li class="inventory-list-row">
            <div class="flex flex-col flex-1 min-w-0">
                <span data-field="name" class="truncate"></span>
                <small data-field="meta" class="text-gray-500 truncate"></small>
            </div>
            <button class="interactive px-2" data-action="edit" title="Edit">&#9998;</button>
        </li>
    </template>

    <template id="table-chip-template">
        <div class="inventory-item" draggable="true" data-type="table">
            <span data-field="name" class="truncate"></span>
            <span data-field="capacity" class="inventory-item-amount"></span>
        </div>
    </template>

    <template id="room-template">
        {{--| The whole room can be dragged to reorder it, while drags starting on a table inside it move that table |--}}
        <section class="inventory-storage" draggable="true">
            <div class="inventory-storage-background"></div>
            <div class="inventory-storage-header">
                <span class="inventory-storage-handle">&#8942;&#8942;</span>
                <span data-field="name" class="font-semibold flex-1 truncate"></span>
                <span data-field="seats" class="text-sm"></span>
                <button class="px-1" data-action="delete" title="Delete room">
                    <img src="{{asset('img/icons/x.svg')}}" alt="delete" class="interactive w-4 h-4" draggable="false">
                </button>
            </div>
            <div class="inventory-storage-items" data-items></div>
        </section>
    </template>

    {{--| Create room modal |--}}
    <x-modal id="room-create-modal">
        <h3 class="title py-1">New Room</h3>
        <form id="room-create-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createRoom()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Background image (optional)">
                <x-form.input.file_uploader name="background_image" accepts="image/*"/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('room-create-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm" onclick="createRoom()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Delete room modal |--}}
    <x-modal id="room-delete-modal">
        <h3 class="title py-1">Delete room</h3>
        <p class="text-center">
            Are you sure you want to delete <span id="room-delete-name" class="font-semibold"></span>?
        </p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('room-delete-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" onclick="deleteRoom()">Delete</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit table modal |--}}
    <x-modal id="table-modal">
        <h3 id="table-modal-title" class="title py-1">New Table</h3>
        <form id="table-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveTable()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Capacity (seats)">
                <input type="number" name="capacity" class="underlined" min="1" value="4" required/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="table-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteModal('table')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('table-modal')">Cancel</button>
            <button id="table-modal-submit" class="interactive px-5 py-2.5" onclick="saveTable()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit customer modal |--}}
    <x-modal id="customer-modal">
        <h3 id="customer-modal-title" class="title py-1">New Customer</h3>
        <form id="customer-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveCustomer()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Description (optional)">
                <textarea name="description" class="underlined" rows="3"></textarea>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="customer-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteModal('customer')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('customer-modal')">Cancel</button>
            <button id="customer-modal-submit" class="interactive px-5 py-2.5" onclick="saveCustomer()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit employee modal |--}}
    <x-modal id="employee-modal">
        <h3 id="employee-modal-title" class="title py-1">New Employee</h3>
        <form id="employee-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveEmployee()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <div class="flex gap-3">
                <x-form.input-wrapper label_text="Prep speed" cls="flex-1">
                    <input type="number" name="prep_speed" class="underlined" min="0" max="100" step="0.01" value="1" required/>
                </x-form.input-wrapper>
                <x-form.input-wrapper label_text="Cleaning speed" cls="flex-1">
                    <input type="number" name="cleaning_speed" class="underlined" min="0" max="100" step="0.01" value="1" required/>
                </x-form.input-wrapper>
            </div>
            <x-form.input-wrapper label_text="Controlled by">
                <select name="player_uuid" class="underlined"></select>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Icon (optional)">
                <x-form.input.file_uploader name="icon" accepts="image/*"/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="employee-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteModal('employee')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('employee-modal')">Cancel</button>
            <button id="employee-modal-submit" class="interactive px-5 py-2.5" onclick="saveEmployee()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit boost modal. Boosts are activated during service, as often as wanted |--}}
    <x-modal id="boost-modal">
        <h3 id="boost-modal-title" class="title py-1">New Boost</h3>
        <form id="boost-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveBoost()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Activated on">
                <select name="target" class="underlined" onchange="updateBoostFields()" required>
                    <option value="equipment">Equipment</option>
                    <option value="meal">A prepped or cooking meal</option>
                    <option value="area">The prep, cooking or cleaning area</option>
                </select>
            </x-form.input-wrapper>
            {{--| The speed bonus adds to the speed of what it is on, as an equipment multiplier does |--}}
            <div class="flex gap-3">
                <x-form.input-wrapper label_text="Speed bonus" cls="flex-1">
                    <input type="number" name="speed_bonus" class="underlined" min="0" max="100" step="0.01" value="0" required/>
                </x-form.input-wrapper>
                <x-form.input-wrapper label_text="For (minutes)" cls="flex-1">
                    <input type="number" name="duration" class="underlined" min="0" max="10080" step="1" value="0" required/>
                </x-form.input-wrapper>
            </div>
            <x-form.input-wrapper label_text="Minutes of work done straight away">
                <input type="number" name="minutes_off" class="underlined" min="0" max="99999" step="0.01" value="0" required/>
            </x-form.input-wrapper>
            <div id="boost-quality-field" class="hidden">
                <x-form.input-wrapper label_text="Quality bonus">
                    <input type="number" name="quality_bonus" class="underlined" min="-9999" max="9999" step="1" value="0"/>
                </x-form.input-wrapper>
            </div>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="boost-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteTypeModal('boost')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('boost-modal')">Cancel</button>
            <button id="boost-modal-submit" class="interactive px-5 py-2.5" onclick="saveBoost()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Invite a player, who controls the employees assigned to them during service |--}}
    <x-modal id="player-modal">
        <h3 class="title py-1">Invite Player</h3>
        <form id="player-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); invitePlayer()">
            <x-form.input-wrapper label_text="Username">
                <input type="text" name="username" class="underlined" maxlength="255" placeholder="Username..." autocomplete="off" required/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('player-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="player-invite" onclick="invitePlayer()">Invite</button>
        </x-slot:buttons>
    </x-modal>

    <x-modal id="player-remove-modal">
        <h3 class="title py-1">Remove player</h3>
        <p class="text-center">
            Are you sure you want to remove <span id="player-remove-name" class="font-semibold"></span>?
        </p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('player-remove-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" onclick="removePlayer()">Remove</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Delete table / customer / employee / boost modal |--}}
    <x-modal id="type-delete-modal">
        <h3 id="type-delete-title" class="title py-1"></h3>
        <p class="text-center">
            Are you sure you want to delete <span id="type-delete-name" class="font-semibold"></span>?
        </p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="cancelDelete()">Cancel</button>
            <button class="interactive px-5 py-2.5" onclick="confirmDelete()">Delete</button>
        </x-slot:buttons>
    </x-modal>
@endsection
