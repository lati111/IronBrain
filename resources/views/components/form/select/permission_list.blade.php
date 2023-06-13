<select id="permissionSelect" name="permission_id" class="underlined py-0
    @isset($classes)$classes @endisset"
    data-url="{{route('config.permission.selector.list')}}"
    dusk="permission_select"
/>
    <option value="">{{$default_option}}</option>
</select>
