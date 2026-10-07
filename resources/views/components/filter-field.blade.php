@props(['name', 'label', 'type' => 'text', 'options' => null])
@php
    $value = old($name, request($name, ''));
    $value = is_scalar($value) ? (string) $value : '';
    $fieldId = 'filter-' . $name;
@endphp
<div class="mb-3">
    <label class="form-label" for="{{ $fieldId }}">{{ $label }}</label>
    @if ($options !== null)
        <select id="{{ $fieldId }}" name="{{ $name }}" {{ $attributes->class(['form-select']) }}
            data-select2-disabled>
            <option value="">All / default</option>
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($value === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
            {{ $attributes->class(['form-control']) }}>
    @endif
    @error($name)
        <span class="field-error text-danger">{{ $message }}</span>
    @enderror
</div>
