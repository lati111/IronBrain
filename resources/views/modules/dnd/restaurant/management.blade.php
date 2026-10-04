@extends('layouts.main')

@section('htmlTitle', $restaurant->name)
@section('title', $restaurant->name)

@section('header')
    @vite([
        'resources/css/modules/dnd/restaurant/restaurant.css',
        'resources/ts/modules/dnd/restaurant/management.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <div id="inventory" data-api-url="{{route('api.dnd.restaurant.inventory.get', ['restaurant_uuid' => $restaurant->uuid])}}">

        {{--| Top bar |--}}
        <div class="flex justify-between items-center mb-4">
            {{--| Both sides share a width, so the title stays centred |--}}
            <div class="w-72 flex gap-4 whitespace-nowrap">
                <a href="{{route('dnd.restaurant.overview')}}" class="interactive">&larr; Restaurants</a>
                <a href="{{route('dnd.restaurant.manage', ['restaurant_uuid' => $restaurant->uuid])}}" class="interactive" dusk="restaurant-manage">Manage restaurant</a>
            </div>
            <h1 class="title">Inventory</h1>
            <div class="w-72 flex justify-end gap-4 whitespace-nowrap">
                <button class="interactive" onclick="openCreateStorageModal()" dusk="storage-create">+ New Storage Space</button>
                @if($restaurant->isInService())
                    <a href="{{route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid])}}" class="interactive font-semibold" dusk="service-continue">Continue service</a>
                @else
                    <button class="interactive font-semibold" onclick="openModal('service-start-modal')" dusk="service-start">Start service</button>
                @endif
            </div>
        </div>

        <div class="flex gap-4 items-start">

            {{--| Sidebar |--}}
            <aside id="inventory-sidebar" class="inventory-sidebar rounded shadow border gray-border">
                <div class="flex items-center border-b">
                    <button class="inventory-sidebar-toggle interactive px-2" onclick="toggleSidebar()" title="Collapse / expand">
                        <span class="inventory-sidebar-toggle-icon">&laquo;</span>
                    </button>
                    <div class="inventory-sidebar-body flex flex-1">
                        <button class="inventory-tab active flex-1 py-2" data-tab="ingredient" onclick="switchTab('ingredient')">Ingredients</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="equipment" onclick="switchTab('equipment')">Equipment</button>
                        <button class="inventory-tab flex-1 py-2" data-tab="recipe" onclick="switchTab('recipe')">Recipes</button>
                    </div>
                </div>

                <div class="inventory-sidebar-body flex flex-col gap-2 p-2">
                    <input id="inventory-search" type="search" class="underlined w-full" placeholder="Search..." oninput="renderSidebar()">
                    <ul id="inventory-list" class="inventory-list"></ul>
                    <button id="inventory-create-type" class="interactive text-sm" onclick="openCreateTypeModal()">+ New Ingredient</button>
                </div>
            </aside>

            {{--| Storage area |--}}
            <div class="flex-1 flex flex-col gap-4 min-w-0">

                {{--| Ingredients brought in during service, saved until they are sorted into a storage space |--}}
                <section id="pile-storage" class="inventory-storage inventory-pile hidden">
                    <div class="inventory-storage-header">
                        <span class="font-semibold">Unsorted</span>
                    </div>
                    <div class="inventory-storage-items" data-items></div>
                </section>

                {{--| General storage. Hidden while empty, which is always the case when the page loads |--}}
                <section id="general-storage" class="inventory-storage inventory-general empty" data-drop-target="general">
                    <div class="inventory-storage-header">
                        <span class="font-semibold">General Storage</span>
                    </div>
                    <div class="inventory-storage-items" data-items></div>
                </section>

                {{--| Storage spaces |--}}
                <div id="storage-list" class="inventory-storage-list"></div>
                <p id="storage-empty" class="hidden text-center text-gray-400 py-6">No storage spaces yet</p>
            </div>
        </div>
    </div>

    {{--| Drop zone for removing items, pinned to the right edge of the viewport while an item is being dragged |--}}
    <div id="discard-edge" data-drop-target="discard">
        <img src="{{asset('img/icons/x.svg')}}" alt="" class="w-6 h-6">
        <span class="text-sm">Remove</span>
    </div>

    {{--| Templates |--}}
    <template id="list-row-template">
        <li class="inventory-list-row">
            <img data-field="icon" alt="" class="inventory-icon">
            <span data-field="name" class="flex-1 truncate"></span>
            <span data-field="stored" class="text-sm text-gray-500" title="Stored"></span>
            <button class="interactive px-2" data-action="edit" title="Edit">&#9998;</button>
            <button class="interactive px-2" data-action="remove" title="Remove">&minus;</button>
            <button class="interactive px-2" data-action="add" title="Add">+</button>
        </li>
    </template>

    <template id="recipe-component-template">
        <li class="recipe-component-row">
            <img data-field="icon" alt="" class="inventory-icon">
            <span data-field="name" class="flex-1 truncate"></span>
            <input type="number" data-field="amount" class="underlined w-16" min="1" required/>
            <button type="button" class="px-1" data-action="remove" title="Remove from recipe">
                <img src="{{asset('img/icons/x.svg')}}" alt="remove" class="interactive w-4 h-4">
            </button>
        </li>
    </template>

    {{--| When a recipe's equipment is used, shown in its row in the recipe modal |--}}
    <template id="equipment-usage-template">
        <span class="flex items-center gap-2">
            <select data-field="stage" class="underlined text-xs">
                @foreach(\App\Enum\DnD\Restaurant\EquipmentStage::cases() as $stage)
                    <option value="{{$stage->value}}" data-container="{{$stage->allowsContainer() ? 1 : 0}}">{{$stage->label()}}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-1 text-xs">
                <input type="checkbox" data-field="container" class="w-3 h-3"> Container
            </label>
        </span>
    </template>

    <template id="item-template">
        <div class="inventory-item" draggable="true">
            <img data-field="icon" alt="" class="inventory-icon" draggable="false">
            <span data-field="name" class="truncate"></span>
            <span data-field="amount" class="inventory-item-amount"></span>
        </div>
    </template>

    <template id="storage-template">
        {{--| The whole space can be dragged to reorder it, while drags starting on an item inside it move that item |--}}
        <section class="inventory-storage" data-drop-target="storage" draggable="true">
            <div class="inventory-storage-background"></div>
            <div class="inventory-storage-header">
                <span class="inventory-storage-handle">&#8942;&#8942;</span>
                <span data-field="name" class="font-semibold flex-1 truncate"></span>
                <span data-field="occupancy" class="text-sm"></span>
                <button class="px-1" data-action="delete" title="Delete storage space">
                    <img src="{{asset('img/icons/x.svg')}}" alt="delete" class="interactive w-4 h-4" draggable="false">
                </button>
            </div>
            <div class="inventory-capacity-bar"><div data-field="capacity-fill"></div></div>
            <div class="inventory-storage-items" data-items></div>
        </section>
    </template>

    {{--| Create storage modal |--}}
    <x-modal id="storage-create-modal">
        <h3 class="title py-1">New Storage Space</h3>
        <form id="storage-create-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createStorage()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Capacity">
                <input type="number" name="capacity" class="underlined" min="1" value="100" required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Expiration multiplier">
                <input type="number" name="expiration_multiplier" class="underlined" min="0" step="0.01" value="1" required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Background image (optional)">
                <x-form.input.file_uploader name="background_image" accepts="image/*"/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('storage-create-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm" onclick="createStorage()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit ingredient modal |--}}
    <x-modal id="ingredient-create-modal">
        <h3 id="ingredient-modal-title" class="title py-1">New Ingredient</h3>
        <form id="ingredient-create-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createIngredient()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Expires in (days)">
                <input type="number" name="expires_in" class="underlined" min="0" value="7" required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Size">
                <input type="number" name="size" class="underlined" min="0.01" step="0.01" value="1" required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Icon (optional)">
                <x-form.input.file_uploader name="icon" accepts="image/*"/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="ingredient-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteTypeModal('ingredient')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('ingredient-create-modal')">Cancel</button>
            <button id="ingredient-modal-submit" class="interactive px-5 py-2.5" onclick="createIngredient()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit equipment modal |--}}
    <x-modal id="equipment-create-modal">
        <h3 id="equipment-modal-title" class="title py-1">New Equipment</h3>
        <form id="equipment-create-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createEquipment()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Category">
                <select name="category" class="underlined" required>
                    @foreach(\App\Enum\DnD\Restaurant\EquipmentCategory::cases() as $category)
                        <option value="{{$category->value}}">{{$category->label()}}</option>
                    @endforeach
                </select>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Description (optional)">
                <textarea name="description" class="underlined" rows="2"></textarea>
            </x-form.input-wrapper>
            <div class="flex gap-3">
                <x-form.input-wrapper label_text="Size" cls="flex-1">
                    <input type="number" name="size" class="underlined" min="0.01" step="0.01" value="1" required/>
                </x-form.input-wrapper>
                <x-form.input-wrapper label_text="Cleaning time (min)" cls="flex-1">
                    <input type="number" name="cleaning_time" class="underlined" min="0" step="0.01" value="0" required/>
                </x-form.input-wrapper>
                <x-form.input-wrapper label_text="Multiplier" cls="flex-1">
                    <input type="number" name="multiplier" class="underlined" min="0" step="0.01" value="1" required/>
                </x-form.input-wrapper>
            </div>
            <x-form.input-wrapper label_text="Storage">
                {{--| A hidden field before the checkbox ensures an explicit 0 is sent when unchecked, instead of the field being left out |--}}
                <input type="hidden" name="requires_storage" value="0"/>
                <x-form.input.checkbox name="requires_storage" value="1" checked right_label="Requires storage"/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Variant of (optional)">
                <select id="equipment-parent-select" name="parent_uuid" class="underlined">
                    <option value="">None</option>
                </select>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="equipment-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteTypeModal('equipment')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('equipment-create-modal')">Cancel</button>
            <button id="equipment-modal-submit" class="interactive px-5 py-2.5" onclick="createEquipment()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Create / edit recipe modal |--}}
    <x-modal id="recipe-modal">
        <h3 id="recipe-modal-title" class="title py-1">New Recipe</h3>
        @php($recipeComponentTabs = ['ingredient' => 'Ingredients', 'equipment' => 'Equipment', 'byproduct' => 'Byproducts'])
        <form id="recipe-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveRecipe()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" name="name" class="underlined" maxlength="64" placeholder="Name..." required/>
            </x-form.input-wrapper>

            {{--| Tabs |--}}
            <div class="flex border-b">
                <button type="button" class="inventory-tab recipe-tab active flex-1 py-2" data-recipe-tab="details" onclick="switchRecipeTab('details')">Details</button>
                @foreach($recipeComponentTabs as $componentType => $componentLabel)
                    <button type="button" class="inventory-tab recipe-tab flex-1 py-2" data-recipe-tab="{{$componentType}}" onclick="switchRecipeTab('{{$componentType}}')">
                        {{$componentLabel}} <span data-recipe-count="{{$componentType}}" class="text-xs"></span>
                    </button>
                @endforeach
            </div>

            {{--| Details |--}}
            <div class="recipe-panel flex flex-col gap-3" data-recipe-panel="details">
                <div class="flex gap-3">
                    <x-form.input-wrapper label_text="Price" cls="flex-1">
                        <input type="number" name="price" class="underlined" min="0" step="0.01" value="0" data-money required/>
                    </x-form.input-wrapper>
                    <x-form.input-wrapper label_text="Portions" cls="flex-1">
                        <input type="number" name="portions" class="underlined" min="1" value="1" required/>
                    </x-form.input-wrapper>
                    <x-form.input-wrapper label_text="Difficulty" cls="flex-1">
                        <input type="number" name="difficulty" class="underlined" min="0" value="0" required/>
                    </x-form.input-wrapper>
                </div>
                <x-form.input-wrapper label_text="Description (optional)">
                    <textarea name="description" class="underlined" rows="2"></textarea>
                </x-form.input-wrapper>
                <div class="flex gap-3">
                    <x-form.input-wrapper label_text="Prep time (min)" cls="flex-1">
                        <input type="number" name="prep_time" class="underlined" min="0" value="0" required/>
                    </x-form.input-wrapper>
                    <x-form.input-wrapper label_text="Cooking time (min)" cls="flex-1">
                        <input type="number" name="cooking_time" class="underlined" min="0" value="0" required/>
                    </x-form.input-wrapper>
                </div>
                <div class="flex gap-3">
                    <x-form.input-wrapper label_text="Fresh for (min)" cls="flex-1">
                        <input type="number" name="fresh_for" class="underlined" min="0" value="0" required/>
                    </x-form.input-wrapper>
                    <x-form.input-wrapper label_text="Good for (hours)" cls="flex-1">
                        <input type="number" name="good_for" class="underlined" min="0" value="0" required/>
                    </x-form.input-wrapper>
                    <x-form.input-wrapper label_text="Expires in (days)" cls="flex-1">
                        <input type="number" name="expires_in" class="underlined" min="0" value="0" required/>
                    </x-form.input-wrapper>
                </div>
            </div>

            {{--| Ingredients and equipment the recipe requires, and the ingredients it produces as byproducts |--}}
            @foreach($recipeComponentTabs as $componentType => $componentLabel)
                <div class="recipe-panel recipe-components hidden" data-recipe-panel="{{$componentType}}">
                    <ul id="recipe-{{$componentType}}-list" class="recipe-component-list"></ul>
                    <div class="flex gap-2 items-center">
                        <select id="recipe-{{$componentType}}-select" class="underlined flex-1"></select>
                        <button type="button" class="interactive px-2 text-sm" onclick="addRecipeComponent('{{$componentType}}')">+ Add</button>
                    </div>
                </div>
            @endforeach
            <button type="submit" class="hidden"></button>
        </form>
        <x-slot:buttons>
            <button id="recipe-modal-delete" class="interactive px-5 py-2.5 hidden" onclick="openDeleteTypeModal('recipe')">Delete</button>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('recipe-modal')">Cancel</button>
            <button id="recipe-modal-submit" class="interactive px-5 py-2.5" onclick="saveRecipe()">Create</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Amount modal, used both for adding stock and for moving part of a stack |--}}
    <x-modal id="amount-modal">
        <h3 id="amount-modal-title" class="title py-1"></h3>
        <form class="w-full" onsubmit="event.preventDefault(); confirmAmount()">
            <x-form.input-wrapper label_text="Amount">
                <input id="amount-modal-input" type="number" class="underlined" min="1" value="1" required/>
            </x-form.input-wrapper>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('amount-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" onclick="confirmAmount()">Confirm</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Start service modal |--}}
    <x-modal id="service-start-modal">
        <h3 class="title py-1">Start service</h3>
        <form id="service-start-form" method="POST" class="flex flex-col gap-3 w-full"
              action="{{route('dnd.restaurant.service.start', ['restaurant_uuid' => $restaurant->uuid])}}">
            @csrf
            <x-form.input-wrapper label_text="Starting time">
                <input type="time" name="time" class="underlined" value="{{substr($restaurant->opening_time, 0, 5)}}" required/>
            </x-form.input-wrapper>
        </form>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('service-start-modal')">Cancel</button>
            <button type="submit" form="service-start-form" class="interactive px-5 py-2.5" dusk="service-start-confirm">Start</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Delete storage modal |--}}
    <x-modal id="storage-delete-modal">
        <h3 class="title py-1">Delete storage space</h3>
        <p class="text-center">
            Are you sure you want to delete <span id="storage-delete-name" class="font-semibold"></span>?
        </p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('storage-delete-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" onclick="deleteStorage()">Delete</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Delete ingredient / equipment / recipe modal |--}}
    <x-modal id="type-delete-modal">
        <h3 id="type-delete-title" class="title py-1"></h3>
        <p class="text-center">
            Are you sure you want to delete <span id="type-delete-name" class="font-semibold"></span>?
        </p>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="cancelDeleteType()">Cancel</button>
            <button class="interactive px-5 py-2.5" onclick="deleteType()">Delete</button>
        </x-slot:buttons>
    </x-modal>
@endsection
