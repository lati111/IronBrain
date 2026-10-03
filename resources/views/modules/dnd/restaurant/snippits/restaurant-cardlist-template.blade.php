<x-datalist.cardlist.template dataprovider_id="restaurant-cardlist" cls="w-72 flex flex-col items-center gap-2">
    <input type="hidden" name="uuid">

    {{--| Name |--}}
    <h4 class="title text-center text-xl">
        <a data-attribute-name="url" data-settable-attribute="href" class="interactive">
            <span data-name="name"></span>
        </a>
    </h4>

    {{--| Ownership |--}}
    <small class="text-body-secondary">
        <span class="hidden" data-show-if-true-name="is_owner">Owned by you</span>
        <span data-hide-if-true-name="is_owner">Shared by <span data-name="owner_name"></span></span>
    </small>

    {{--| Currency |--}}
    <div class="flex justify-center gap-3 pt-2 mt-1 border-t border-gray-200 w-full">
        <span title="Gold"><span data-name="gold"></span> gp</span>
        <span title="Silver"><span data-name="silver"></span> sp</span>
        <span title="Copper"><span data-name="copper"></span> cp</span>
        <span title="Bits"><span data-name="bits"></span> bits</span>
    </div>
</x-datalist.cardlist.template>
