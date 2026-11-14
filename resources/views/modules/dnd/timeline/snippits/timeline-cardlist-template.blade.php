<x-datalist.cardlist.template dataprovider_id="timeline-cardlist" cls="w-72 flex flex-col items-center gap-2">
    <input type="hidden" name="uuid">

    {{--| Cover |--}}
    <a data-attribute-name="url" data-settable-attribute="href" class="hidden w-full" data-show-if-true-name="has_cover">
        <img data-name="cover_url" data-alt-name="name" class="w-full h-40 object-cover rounded">
    </a>

    {{--| Name |--}}
    <h4 class="title text-center text-xl">
        <a data-attribute-name="url" data-settable-attribute="href" class="interactive">
            <span data-name="name"></span>
        </a>
    </h4>

    {{--| Description |--}}
    <p class="text-body-secondary text-sm text-center line-clamp-3">
        <span data-name="description"></span>
    </p>
</x-datalist.cardlist.template>
