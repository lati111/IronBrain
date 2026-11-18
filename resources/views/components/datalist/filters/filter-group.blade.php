<div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
    {{$slot->hasActualContent() ? $slot : $content}}
</div>
