<div class="flex justify-center items-center h-8 grow max-w-64">
    <label class="flex w-full h-8">
        <span class="input_prepend">Per pagina:</span>
        <select id="{{$id}}" name="perpage" class="flex-grow h-8 py-0">
            @if(isset($options))
                @foreach($options as $option)
                    <option value="{{$option}}" @if($option === ($selected_option ?? null))selected @endif>{{$option}}</option>
                @endforeach
            @else
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            @endif
        </select>
    </label>
</div>
