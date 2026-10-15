@extends('layouts.main')

@section('htmlTitle', $restaurant->name)
@section('title', $restaurant->name)

@section('header')
    @vite([
        'resources/css/modules/dnd/restaurant/restaurant.css',
        'resources/ts/modules/dnd/restaurant/service.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <div id="service"
         data-api-url="{{route('api.dnd.restaurant.floor.get', ['restaurant_uuid' => $restaurant->uuid])}}"
         data-service-url="{{route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $restaurant->uuid])}}"
         data-management-url="{{route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid])}}"
         data-player="{{$isPlayer ? 1 : 0}}">

        {{--| Top bar. Both sides share a width, so the title stays centred |--}}
        <div class="flex justify-between items-center mb-2">
            <a href="{{route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid])}}" class="interactive w-80">&larr; Management</a>
            <h1 class="title">Service</h1>

            {{--| Service clock. The time can be typed in, or moved forward with the buttons |--}}
            <div class="w-80 flex flex-col items-end gap-0.5">
                <div class="flex items-center gap-2">
                    <small id="service-date" class="text-gray-500"></small>
                    <input id="service-time" type="time" class="underlined service-clock" title="Current time" onchange="setServiceTime(this.value)">
                </div>
                {{--| Players only control their employees, so the clock runs as whoever runs the service moves it |--}}
                @unless($isPlayer)
                    <div class="flex items-center gap-1">
                        @foreach([1 => '+1m', 5 => '+5m', 15 => '+15m', 60 => '+1h'] as $minutes => $label)
                            <button class="service-clock-step interactive" onclick="advanceTime({{$minutes}})" dusk="advance-{{$minutes}}">{{$label}}</button>
                        @endforeach
                        <button class="interactive text-sm font-semibold ml-2" onclick="openEndServiceModal()" dusk="service-end">End service</button>
                    </div>
                @endunless
                <small class="text-gray-500">
                    Open {{substr($restaurant->opening_time, 0, 5)}} &ndash; {{substr($restaurant->closing_time, 0, 5)}}
                    &middot; shift total <span id="service-shift-total" class="font-semibold">0b</span>
                </small>
            </div>
        </div>

        <div class="flex gap-3 items-start">

            {{--| Sidebar |--}}
            {{--| Collapsed until opened, as the floor and kitchen need the room during service. Players have none |--}}
            @unless($isPlayer)
            <aside id="inventory-sidebar" class="inventory-sidebar collapsed rounded shadow border gray-border">
                <div class="flex items-center border-b">
                    <button class="inventory-sidebar-toggle interactive px-2" onclick="toggleSidebar()" title="Collapse / expand">
                        <span class="inventory-sidebar-toggle-icon">&laquo;</span>
                    </button>
                    <div class="inventory-sidebar-body flex flex-1">
                        <button class="inventory-tab active flex-1 py-2" data-tab="customer" onclick="switchTab('customer')">Customers</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="ingredient" onclick="switchTab('ingredient')">Ingredients</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="conversion" onclick="switchTab('conversion')">Conversions</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="sale" onclick="switchTab('sale')">Sales</button>
                    </div>
                </div>

                <div class="inventory-sidebar-body flex flex-col gap-2 p-2">
                    <input id="inventory-search" type="search" class="underlined w-full" placeholder="Search..." oninput="renderSidebar()">
                    <ul id="inventory-list" class="inventory-list"></ul>
                    <button id="customer-create-button" class="interactive text-sm" onclick="openCreateCustomerModal()" dusk="customer-create">+ New Customer</button>
                </div>
            </aside>
            @endunless

            {{--| Floor |--}}
            <div class="service-floor flex-1 flex flex-col gap-2">

                {{--| Entrance |--}}
                {{--| Hidden while nobody is waiting, which is always the case when the page loads |--}}
                <section id="service-entrance" class="inventory-storage inventory-general empty">
                    <div class="inventory-storage-header">
                        <span class="font-semibold">Entrance</span>
                    </div>
                    <div class="inventory-storage-items" data-items></div>
                </section>

                {{--| Rooms with their tables |--}}
                <div id="service-rooms" class="flex flex-col gap-2"></div>
                <p id="service-empty" class="hidden text-center text-gray-400 py-6">No tables yet</p>
            </div>

            {{--| Kitchen |--}}
            <aside class="service-kitchen rounded shadow border gray-border">
                <div class="service-kitchen-title">Kitchen</div>

                {{--| The areas sit side by side when there is room for it |--}}
                <div class="kitchen-areas">
                    {{--| Employees, dragged to the prep or cleaning area to work there, and back here to stop |--}}
                    <section id="kitchen-staff-area" class="kitchen-area kitchen-staff-area">
                        <h4 class="kitchen-area-title">Staff</h4>
                        <div id="kitchen-staff" class="kitchen-staff"></div>
                    </section>

                    <section id="kitchen-serving-area" class="kitchen-area">
                        <h4 class="kitchen-area-title">Serving</h4>
                        <div id="kitchen-serving" class="kitchen-meal-list kitchen-drop-zone"></div>
                    </section>

                    <section class="kitchen-area">
                        <h4 class="kitchen-area-title">Cooking</h4>
                        <div id="kitchen-cookers" class="kitchen-cookers"></div>
                    </section>

                    <section id="kitchen-prep-area" class="kitchen-area kitchen-work-area">
                        <div class="flex justify-between items-center gap-2">
                            <h4 class="kitchen-area-title">Preparation</h4>
                            <div class="kitchen-area-staff" data-station-staff="prep"></div>
                            @unless($isPlayer)
                                <button class="interactive text-xs" onclick="openPlanMealModal()" dusk="meal-plan">+ Plan meal</button>
                            @endunless
                        </div>
                        <div class="kitchen-total-progress hidden" data-station-progress="prep"><div></div></div>
                        <div id="kitchen-preparation" class="kitchen-meal-list"></div>
                    </section>

                    <section id="kitchen-cleaning-area" class="kitchen-area kitchen-work-area">
                        <div class="flex justify-between items-center gap-2">
                            <h4 class="kitchen-area-title">Equipment</h4>
                            <div class="kitchen-area-staff" data-station-staff="cleaning"></div>
                            @unless($isPlayer)
                                <button id="kitchen-clean-all" class="interactive text-xs disabled:opacity-40" onclick="cleanAllEquipment()" dusk="clean-all">Clean all</button>
                            @endunless
                        </div>
                        <div class="kitchen-total-progress hidden" data-station-progress="cleaning"><div></div></div>

                        <ul id="kitchen-equipment" class="kitchen-equipment"></ul>
                    </section>
                </div>
            </aside>
        </div>
    </div>

    {{--| Drop zone for unseating customers, pinned to the right edge of the viewport while a customer is dragged |--}}
    <div id="discard-edge">
        <img src="{{asset('img/icons/x.svg')}}" alt="" class="w-6 h-6">
        <span class="text-sm">Remove</span>
    </div>

    {{--| Templates |--}}
    <template id="list-row-template">
        <li class="inventory-list-row">
            <div class="flex flex-col flex-1 min-w-0">
                <span data-field="name" class="truncate"></span>
                <small data-field="meta" class="text-gray-500 truncate"></small>
            </div>
            <button class="interactive px-2" data-action="add" title="Bring to the entrance">+</button>
        </li>
    </template>

    <template id="customer-chip-template">
        <div class="inventory-item service-customer" draggable="true">
            <span data-field="name" class="truncate"></span>
        </div>
    </template>

    <template id="room-template">
        <section class="service-room rounded shadow border gray-border">
            <div class="inventory-storage-header">
                <span data-field="name" class="font-semibold flex-1 truncate"></span>
                <span data-field="occupancy" class="text-sm"></span>
            </div>
            <div class="service-tables" data-tables></div>
        </section>
    </template>

    {{--| Clicking a table, outside its seats, opens its menu to take or look at orders |--}}
    <template id="table-template">
        <div class="service-table">
            <div class="flex items-center gap-2">
                <span data-field="name" class="font-semibold truncate flex-1"></span>
                <span data-field="occupancy" class="text-sm text-gray-500"></span>
                {{--| The employee waiting the table, dropped on it from the staff |--}}
                <span class="service-table-waiter" data-waiter></span>
            </div>
            <span data-field="order" class="service-table-order"></span>
            {{--| The job the waiter is on at the table, such as taking its order or bringing a plate |--}}
            <div data-waiter-job class="service-table-job hidden">
                <span data-field="job" class="truncate"></span>
                <div class="service-table-job-bar"><div data-waiter-job-fill></div></div>
            </div>
            {{--| How many of the ordered portions have been served |--}}
            <div data-order-progress class="service-table-progress hidden">
                <div class="service-table-progress-bar"><div data-order-progress-fill class="service-table-progress-fill"></div></div>
                <span data-field="order-progress" class="service-table-progress-count"></span>
            </div>
            <div class="service-seats" data-seats></div>
        </div>
    </template>

    <template id="seat-template">
        <div class="service-seat">
            <span data-field="number" class="service-seat-number"></span>
        </div>
    </template>

    {{--| The colour of a meal shows its status: planned, prepped, cooking, done or spoiled |--}}
    <template id="meal-card-template">
        <div class="kitchen-meal">
            <span data-field="name" class="font-semibold truncate flex-1 min-w-0"></span>
            <span data-field="quality" class="kitchen-meal-quality"></span>
            <span data-field="needs" class="kitchen-meal-needs" title="Meals still to be put in"></span>
            <span data-field="timer" class="text-xs text-gray-500 whitespace-nowrap"></span>
            <div class="kitchen-meal-actions flex gap-2 text-xs whitespace-nowrap" data-actions></div>
        </div>
    </template>

    {{--| An employee, with their name and the task they are on below them |--}}
    <template id="employee-template">
        <div class="kitchen-employee" draggable="true">
            <div class="kitchen-employee-icon" data-field="initial"></div>
            <span data-field="name" class="kitchen-employee-name"></span>
            <small data-field="task" class="kitchen-employee-task"></small>
        </div>
    </template>

    <template id="cooker-slot-template">
        <div class="kitchen-cooker kitchen-drop-zone">
            <span data-field="name" class="text-sm font-semibold"></span>
            <div data-meal></div>
        </div>
    </template>

    {{--| Plan meal modal |--}}
    <x-modal id="meal-plan-modal">
        <h3 class="title py-1">Plan a meal</h3>
        <form id="meal-plan-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); planMeal()">
            <x-form.input-wrapper label_text="Recipe">
                <select id="meal-plan-recipe" name="recipe_uuid" class="underlined" required onchange="renderMealPlanChoices()"></select>
            </x-form.input-wrapper>
            {{-- Which ingredient to use for each category the recipe takes --}}
            <div id="meal-plan-choices" class="contents"></div>
            <button type="submit" class="hidden"></button>
        </form>
        <template id="meal-plan-choice-template">
            <x-form.input-wrapper label_text="">
                <select class="underlined" required></select>
            </x-form.input-wrapper>
        </template>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('meal-plan-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm" onclick="planMeal()">Plan</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Meal menu, hovering over a meal in a cooker or being served, listing what can be done with it |--}}
    <div id="meal-menu" class="meal-menu hidden" role="menu">
        <small id="meal-menu-status" class="meal-menu-status"></small>
        <div id="meal-menu-options" class="flex flex-col"></div>
    </div>

    {{--| How well a meal turned out, asked for once it is done cooking or ready to serve |--}}
    <x-modal id="meal-quality-modal">
        <h3 id="meal-quality-title" class="title py-1"></h3>
        <form id="meal-quality-form" class="w-full" onsubmit="event.preventDefault(); saveQuality()">
            <x-form.input-wrapper label_text="Quality">
                <input id="meal-quality-input" type="number" name="quality" class="underlined" min="-9999" max="9999" step="1" required/>
            </x-form.input-wrapper>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="putOffQuality()">Later</button>
            <button class="interactive px-5 py-2.5" dusk="meal-quality-save" onclick="saveQuality()">Save</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Confirmation before wasting a meal's ingredients |--}}
    <x-modal id="meal-scrap-modal">
        <h3 class="title py-1">Scrap meal</h3>
        <p class="text-center">
            Are you sure you want to scrap <span id="meal-scrap-name" class="font-semibold"></span>?
        </p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('meal-scrap-modal')">Keep</button>
            <button class="interactive px-5 py-2.5" onclick="confirmScrapMeal()">Scrap</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Put a meal into another, plating the portions left over |--}}
    <x-modal id="meal-supply-modal">
        <h3 class="title py-1">Plate the leftovers?</h3>
        <p id="meal-supply-text" class="text-center"></p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('meal-supply-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm" onclick="confirmSupplyMeal()">Put in &amp; plate</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Take order modal. Recipes picked on the left each add a portion to the order on the right, for one customer |--}}
    <x-modal id="order-modal">
        <h3 id="order-modal-title" class="title py-1">Take order</h3>
        <div class="order-columns w-full">
            <section class="flex flex-col gap-1 min-w-0">
                <span class="kitchen-area-title">Recipes</span>
                <input id="order-recipe-search" type="search" class="underlined w-full text-sm" placeholder="Search..." oninput="renderOrderRecipes()">
                <ul id="order-recipes" class="order-list"></ul>
            </section>
            <section class="flex flex-col gap-1 min-w-0">
                <span class="kitchen-area-title">Order</span>
                <ul id="order-items" class="order-list"></ul>
                <p class="text-right text-sm">Total: <span id="order-total" class="font-semibold"></span></p>
            </section>
        </div>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('order-modal')">Cancel</button>
            <button id="order-place" class="interactive px-5 py-2.5 disabled:opacity-40" dusk="order-place" onclick="placeOrder()">Place order</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Order details, where portions are marked as eaten, a tip or discount is given, and the order is finished |--}}
    <x-modal id="order-details-modal">
        <h3 id="order-details-title" class="title py-1"></h3>
        <p id="order-details-status" class="text-center text-sm text-gray-500"></p>
        <ul id="order-details-items" class="w-full"></ul>
        <div class="flex gap-3 w-full">
            <x-form.input-wrapper label_text="Discount" cls="flex-1">
                <input id="order-discount" type="number" class="underlined" min="0" step="0.01" data-money/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Tip" cls="flex-1">
                <input id="order-tip" type="number" class="underlined" min="0" step="0.01" data-money/>
            </x-form.input-wrapper>
        </div>
        <div class="order-details-totals w-full">
            <span>Items</span><span id="order-details-subtotal"></span>
            <span class="font-semibold">Total</span><span id="order-details-total" class="font-semibold"></span>
        </div>
        <x-slot:buttons>
            <button class="interactive px-5 py-2.5" dusk="order-cancel" onclick="openCancelOrderModal()">Cancel order</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('order-details-modal')">Close</button>
            <button class="interactive px-5 py-2.5" dusk="order-edit" onclick="editOrder()">Edit</button>
            <button id="order-consume" class="interactive px-5 py-2.5 hidden" dusk="order-consume" onclick="consumeOrder()">All consumed</button>
            <button class="interactive px-5 py-2.5" dusk="order-finish" onclick="finishOrder()">Finish order</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Confirmation before cancelling an order |--}}
    <x-modal id="order-cancel-modal">
        <h3 class="title py-1">Cancel order</h3>
        <p class="text-center">Are you sure you want to cancel this order?</p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="keepOrder()">Keep</button>
            <button class="interactive px-5 py-2.5" dusk="order-cancel-confirm" onclick="cancelOrder()">Cancel order</button>
        </x-slot:buttons>
    </x-modal>

    {{--| End service modal, listing what was sold and the profit made. Only whoever runs the service ends it |--}}
    @unless($isPlayer)
    <x-modal id="service-end-modal">
        <h3 class="title py-1">End service</h3>
        <p id="service-end-hours" class="text-center text-sm text-gray-500"></p>
        <ul id="service-end-items" class="w-full"></ul>
        <p class="w-full text-right">Profit: <span id="service-end-profit" class="font-semibold"></span></p>
        {{--| Moving on to the next day ages the stored ingredients, as the +1 day button on the inventory page does |--}}
        <label class="w-full flex items-center gap-2 text-sm">
            <input id="service-end-advance-day" type="checkbox" checked dusk="service-end-advance-day">
            Move on to the next day
        </label>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('service-end-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="service-end-confirm" onclick="endService()">End service</button>
        </x-slot:buttons>
    </x-modal>
    @endunless

    {{--| Adding an ingredient brought in during service to the pile |--}}
    {{--| Queue a conversion in the prep area |--}}
    <x-modal id="conversion-modal">
        <h3 id="conversion-modal-title" class="title py-1"></h3>
        <form id="conversion-form" class="w-full" onsubmit="event.preventDefault(); queueConversion()">
            <x-form.input-wrapper label_text="Amount">
                <input id="conversion-amount" type="number" name="amount" class="underlined" min="0.01" step="0.01" value="1" required/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('conversion-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="conversion-confirm" onclick="queueConversion()">Convert</button>
        </x-slot:buttons>
    </x-modal>

    <x-modal id="pile-modal">
        <h3 id="pile-modal-title" class="title py-1"></h3>
        <form id="pile-form" class="w-full" onsubmit="event.preventDefault(); addToPile()">
            <x-form.input-wrapper label_text="Amount">
                <input id="pile-amount" type="number" name="amount" class="underlined" min="0.01" step="0.01" value="1" required/>
            </x-form.input-wrapper>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('pile-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="pile-confirm" onclick="addToPile()">Add</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Putting a recipe on sale for this service, or taking it off sale |--}}
    <x-modal id="sale-modal">
        <h3 id="sale-modal-title" class="title py-1"></h3>
        <form id="sale-form" class="w-full" onsubmit="event.preventDefault(); saveSale()">
            <x-form.input-wrapper label_text="Price">
                <input id="sale-price" type="number" name="price" class="underlined" min="0" step="0.01" data-money required/>
            </x-form.input-wrapper>
        </form>
        <x-slot:buttons>
            <button id="sale-remove" class="interactive px-5 py-2.5 hidden" onclick="removeSale()">Remove sale</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('sale-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="sale-confirm" onclick="saveSale()">Save</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create customer modal |--}}
    <x-modal id="customer-create-modal">
        <h3 class="title py-1">New Customer</h3>
        <form id="customer-create-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createCustomer()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Description (optional)">
                <textarea name="description" class="underlined" rows="3"></textarea>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('customer-create-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm" onclick="createCustomer()">Create</button>
        </x-slot:buttons>
    </x-modal>
@endsection
