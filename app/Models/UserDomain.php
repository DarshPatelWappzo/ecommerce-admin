<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\UserDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'domain_name'])]
class UserDomain extends Model
{
    /** @use HasFactory<UserDomainFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public function auditModule(): string
    {
        return 'users';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function auditValues(array $values): array
    {
        if (array_key_exists('domain_name', $values)) {
            $values['domain'] = $values['domain_name'];
            unset($values['domain_name']);
        }

        return $values;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
