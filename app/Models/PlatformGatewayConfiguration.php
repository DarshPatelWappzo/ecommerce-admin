<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformGatewayConfiguration extends Model
{
    protected $fillable = ['gateway', 'mode', 'is_enabled'];

    protected $attributes = ['gateway' => 'razorpay', 'mode' => 'test', 'is_enabled' => false];

    public function getConnectionName(): string
    {
        return config('subscriptions.connection');
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
