<div class="flex justify-center">
    <table class="datatable table table-hover table-striped"
        data-content-url="{{$dataUrl}}"
        @isset($table_size) data-table-size="{{$table_size}}" @endisset
    />
        <thead>
            <tr>
                {{$headers}}
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
