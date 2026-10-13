<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformWebhookEvent extends Model
{
    protected $fillable = ['configuration_version_id', 'event_id', 'body_hash', 'event_type', 'payment_id'];

    public function getConnectionName(): string
    {
        return config('subscriptions.connection');
    }

    protected function casts(): array
    {
        return [];
    }
}
