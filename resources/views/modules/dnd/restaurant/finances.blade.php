@extends('layouts.main')

@section('htmlTitle', $restaurant->name)
@section('title', $restaurant->name)

@section('header')
    @vite([
        'resources/css/modules/dnd/restaurant/restaurant.css',
        'resources/ts/modules/dnd/restaurant/finances.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <div id="finances" data-api-url="{{route('api.dnd.restaurant.finances.get', ['restaurant_uuid' => $restaurant->uuid])}}">

        {{--| Top bar |--}}
        <div class="flex justify-between items-center mb-4">
            <a href="{{route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid])}}" class="interactive w-64">&larr; Management</a>
            <h1 class="title">Finances</h1>
            <button class="interactive w-64 text-right" onclick="openTransactionModal()" dusk="transaction-create">+ New Transaction</button>
        </div>

        {{--| The transactions, the most recent first |--}}
        <ul id="finance-list" class="finance-list"></ul>
    </div>

    <template id="finance-row-template">
        <li class="finance-row" role="button" tabindex="0">
            <span data-field="date" class="finance-row-date"></span>
            <span data-field="title" class="flex-1 truncate"></span>
            <span data-field="amount" class="finance-amount"></span>
        </li>
    </template>

    {{--| The details of a transaction |--}}
    <x-modal id="finance-modal">
        <div class="finance-details">
            <h3 id="finance-modal-title" class="title py-1 text-center"></h3>
            <p id="finance-modal-meta" class="text-center text-sm text-gray-500"></p>
            {{--| More about the transaction, when it has a description |--}}
            <textarea id="finance-modal-description" class="finance-description hidden" rows="4" readonly></textarea>
            <div id="finance-modal-body" class="flex flex-col gap-4 mt-4"></div>
        </div>
    </x-modal>

    {{--| Add a transaction by hand |--}}
    <x-modal id="transaction-modal">
        <h3 class="title py-1">New Transaction</h3>
        <form id="transaction-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createTransaction()">
            <x-form.input-wrapper label_text="Title">
                <input type="text" name="title" class="underlined" maxlength="255" placeholder="Title..." required/>
            </x-form.input-wrapper>
            <div class="flex gap-3">
                <x-form.input-wrapper label_text="Money" cls="w-32">
                    <select name="direction" class="underlined">
                        <option value="in">In</option>
                        <option value="out">Out</option>
                    </select>
                </x-form.input-wrapper>
                <x-form.input-wrapper label_text="Amount" cls="flex-1">
                    <input type="number" name="amount" class="underlined" min="0.01" step="0.01" value="0" data-money required/>
                </x-form.input-wrapper>
            </div>
            <x-form.input-wrapper label_text="Description (optional)">
                <textarea name="description" class="underlined" rows="4" placeholder="Description..."></textarea>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('transaction-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="transaction-confirm" onclick="createTransaction()">Add</button>
        </x-slot:buttons>
    </x-modal>
@stop
