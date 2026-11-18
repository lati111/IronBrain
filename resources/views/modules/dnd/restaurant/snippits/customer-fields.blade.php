{{--| What players know a customer as until revealed, and how they tip. Shared by the customer forms of the manage and service pages |--}}
<div class="flex gap-3 items-end">
    <x-form.input-wrapper label_text="Hidden name (optional)" cls="flex-1">
        <input type="text" name="hidden_name" class="underlined" maxlength="64" placeholder="{{\App\Models\DnD\Restaurant\Customer::UNKNOWN_NAME}}" title="What players see until the name is revealed"/>
    </x-form.input-wrapper>
    <x-form.input-wrapper label_text="Players see">
        {{--| A hidden field before the checkbox ensures an explicit 0 is sent when unchecked, instead of the field being left out |--}}
        <input type="hidden" name="revealed" value="0"/>
        <x-form.input.checkbox name="revealed" value="1" right_label="Real name"/>
    </x-form.input-wrapper>
</div>
<div class="flex gap-3">
    <x-form.input-wrapper label_text="Tip expectation" cls="flex-1">
        <input type="number" name="tip_expectation" class="underlined" min="1" max="5" step="1" value="3" title="How satisfied, from 1 to 5, a portion has to leave them to tip for it" required/>
    </x-form.input-wrapper>
    <x-form.input-wrapper label_text="Tip rate (%)" cls="flex-1">
        <input type="number" name="tip_rate" class="underlined" min="0" max="10000" step="0.01" value="0" title="What they tip per portion as a share of its price, for meeting their expectation, twice that for one above it, and so on" required/>
    </x-form.input-wrapper>
    <x-form.input-wrapper label_text="Max tip (bits)" cls="flex-1">
        <input type="number" name="tip_cap" class="underlined" min="0" step="0.01" value="0" title="The most they tip for an order" required/>
    </x-form.input-wrapper>
</div>
