<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformGatewayVersion extends Model
{
    protected $fillable = ['configuration_id', 'mode', 'credentials'];

    protected $hidden = ['credentials'];

    public function getConnectionName(): string
    {
        return config('subscriptions.connection');
    }

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array'];
    }
}
