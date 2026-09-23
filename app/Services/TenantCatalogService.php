<?php

namespace App\Services;

use App\Repositories\TenantCatalogRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantCatalogService
{
    public function __construct(private readonly TenantCatalogRepository $catalog) {}

    public function save(array $data, bool $tag): Model
    {
        if (! $tag) {
            if ($data['is_variant'] && $data['type'] !== 'select') {
                throw ValidationException::withMessages(['type' => 'Choose Select as the type for an attribute used for variants.']);
            }
            if ($data['type'] === 'select' && count($data['options']) === 0) {
                throw ValidationException::withMessages(['options' => 'Add at least one option with a value and label for this select attribute.']);
            }
            if ($data['type'] !== 'select' && count($data['options']) > 0) {
                throw ValidationException::withMessages(['options' => 'Only select attributes can have options.']);
            }
        }
        try {
            return DB::connection('tenant')->transaction(fn() => $tag ? $this->catalog->saveTag($data) : $this->catalog->saveAttribute($data));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([$tag ? 'slug' : 'options' => 'The code, slug, or option value is already in use.']);
        }
    }
}
