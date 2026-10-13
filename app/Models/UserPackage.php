<?php

namespace App\Models;

use Database\Factories\UserPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'package_id', 'start_date', 'end_date', 'status', 'billing_cycle', 'grace_expires_at', 'tenant_database_id', 'package_payment_id', 'mode', 'price_snapshot'])]
class UserPackage extends Model
{
    /** @use HasFactory<UserPackageFactory> */
    use HasFactory, SoftDeletes;

    public function getConnectionName(): string
    {
        return config('subscriptions.connection');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'array',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'grace_expires_at' => 'datetime',
        ];
    }
}
