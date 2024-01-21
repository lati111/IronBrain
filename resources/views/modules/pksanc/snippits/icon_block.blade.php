<div class='flex flex-col justify-center items-center h-full w-8 my-2 gap-2'>
    @foreach($icons as $icon)
        <img src='{{$icon['src']}}' alt='{{$icon['alt']}}' title='{{$icon['title']}}' class='h-{{$icon['height']}}'>
    @endforeach
</div>
