<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <label class="form-label mb-0">Domains</label>
        <button class="btn btn-sm btn-outline-primary" type="button" data-add-domain>
            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add domain
        </button>
    </div>
    <div data-domain-fields>
        @foreach ($domains as $index => $domain)
            <div class="mb-2" data-domain-row>
                <div class="input-group">
                    <input class="form-control" id="domain-{{ $index }}" name="domains[]" type="text"
                        value="{{ $domain }}" placeholder="example.com" data-domain-input>
                    <button class="btn btn-outline-danger" type="button" data-remove-domain>
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        <span class="visually-hidden">Remove domain</span>
                    </button>
                </div>
                <small class="field-error" data-error-for="domains.{{ $index }}">
                    @error('domains.' . $index)
                        {{ $message }}
                    @enderror
                </small>
            </div>
        @endforeach
    </div>
    <small class="field-error" data-error-for="domains">
        @error('domains')
            {{ $message }}
        @enderror
    </small>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/admin-domains.js') }}"></script>
    @endpush
@endonce
