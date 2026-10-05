<div class="row g-3">
    @foreach (['name' => 'Recipient name', 'phone' => 'Phone', 'address_line_1' => 'Address line 1', 'address_line_2' => 'Address line 2 (optional)', 'city' => 'City', 'postal_code' => 'Postal code'] as $field => $label)
        <div class="col-md-6"><label class="form-label"
                for="{{ $type }}-{{ $field }}">{{ $label }}</label><input class="form-control"
                id="{{ $type }}-{{ $field }}" name="{{ $type }}[{{ $field }}]"
                value="{{ data_get($input, $type . '.' . $field) }}"
                maxlength="{{ in_array($field, ['address_line_1', 'address_line_2']) ? 255 : ($field === 'name' ? 200 : ($field === 'phone' ? 30 : ($field === 'postal_code' ? 20 : 100))) }}">
        </div>
    @endforeach
    <div class="col-md-6"><label class="form-label" for="{{ $type }}-state">State / Union
            territory</label><select class="form-select" id="{{ $type }}-state"
            name="{{ $type }}[state_code]">
            <option value="">Select state</option>
            @foreach (config('customer_locations.IN.states') as $code => $name)
                <option value="{{ $code }}" @selected(data_get($input, $type . '.state_code') === $code)>{{ $name }}</option>
            @endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label" for="{{ $type }}-country">Country</label><select
            class="form-select" id="{{ $type }}-country" name="{{ $type }}[country_code]">
            <option value="IN">India</option>
        </select></div>
</div>
