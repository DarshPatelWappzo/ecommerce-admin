<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PlatformGatewayConfiguration;
use App\Models\PlatformGatewayVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformGatewayConfigurationService
{
    /** @param array{mode: string, is_enabled: bool|int|string, key_id?: ?string, key_secret?: ?string, webhook_secret?: ?string} $input */
    public function save(array $input, int $actorId): void
    {
        $connection = config('subscriptions.connection');
        DB::connection($connection)->transaction(function () use ($input, $actorId, $connection): void {
            PlatformGatewayConfiguration::query()->firstOrCreate(['gateway' => 'razorpay']);
            $config = PlatformGatewayConfiguration::query()
                ->where('gateway', 'razorpay')
                ->lockForUpdate()
                ->firstOrFail();
            $previous = $this->version($config->id, $input['mode']);
            $credentials = $previous?->credentials ?? [];
            foreach (['key_id', 'key_secret', 'webhook_secret'] as $field) {
                if (filled($input[$field] ?? null)) {
                    $credentials[$field] = $input[$field];
                }
            }
            if (isset($credentials['key_id']) && ! preg_match('/^rzp_' . $input['mode'] . '_[A-Za-z0-9]+$/', $credentials['key_id'])) {
                throw ValidationException::withMessages(['key_id' => 'The Key ID must match the selected environment.']);
            }
            if ($previous && ($credentials['key_id'] ?? null) !== ($previous->credentials['key_id'] ?? null) && blank($input['key_secret'] ?? null)) {
                throw ValidationException::withMessages(['key_secret' => 'Enter the matching secret when changing the Key ID.']);
            }
            if ($input['is_enabled']) {
                foreach (['key_id', 'key_secret'] as $field) {
                    if (blank($credentials[$field] ?? null)) {
                        throw ValidationException::withMessages([$field => 'Configure the Key ID and Key secret before enabling Razorpay.']);
                    }
                }
            }
            if ($credentials !== [] && (! $previous || $previous->credentials !== $credentials)) {
                $previous = PlatformGatewayVersion::create(['configuration_id' => $config->id, 'mode' => $input['mode'], 'credentials' => $credentials]);
            }
            $config->update(['mode' => $input['mode'], 'is_enabled' => (bool) $input['is_enabled']]);
            AuditLog::on($connection)->create([
                'user_id' => $actorId,
                'action' => 'configuration_saved',
                'module' => 'platform_payments',
                'auditable_type' => PlatformGatewayConfiguration::class,
                'auditable_id' => $config->id,
                'new_values' => ['gateway' => 'razorpay', 'mode' => $config->mode, 'is_enabled' => $config->is_enabled, 'version_id' => $previous?->id],
                'description' => 'Platform payment configuration updated',
            ]);
        }, 3);
    }

    public function version(int $configurationId, string $mode): ?PlatformGatewayVersion
    {
        return PlatformGatewayVersion::query()
            ->where('configuration_id', $configurationId)
            ->where('mode', $mode)
            ->latest('id')
            ->first();
    }

    public function active(): PlatformGatewayVersion
    {
        $config = PlatformGatewayConfiguration::query()
            ->where('gateway', 'razorpay')
            ->first();
        $version = $config?->is_enabled ? $this->version($config->id, $config->mode) : null;
        if (! $version || blank($version->credentials['key_id'] ?? null) || blank($version->credentials['key_secret'] ?? null)) {
            throw ValidationException::withMessages(['gateway' => 'Platform payment configuration is unavailable. Please contact support.']);
        }

        return $version;
    }

    /** @return array{mode: string, is_enabled: bool, environments: array<string, array<string, mixed>>} */
    public function status(): array
    {
        $config = PlatformGatewayConfiguration::query()
            ->where('gateway', 'razorpay')
            ->first();
        $result = ['mode' => $config?->mode ?? 'test', 'is_enabled' => $config?->is_enabled ?? false, 'environments' => []];
        foreach (['test', 'live'] as $mode) {
            $version = $config ? $this->version($config->id, $mode) : null;
            $result['environments'][$mode] = [
                'version_id' => $version?->id,
                'configured' => array_map(fn(string $key): bool => filled($version?->credentials[$key] ?? null), array_combine(['key_id', 'key_secret', 'webhook_secret'], ['key_id', 'key_secret', 'webhook_secret'])),
            ];
        }

        return $result;
    }
}
