<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('subscriptions.connection'));
        if ($schema->getConnection()->getDriverName() === 'mysql') {
            foreach (['users', 'user_packages', 'audit_logs'] as $transactionalTable) {
                $schema->getConnection()->statement('ALTER TABLE `'.$transactionalTable.'` ENGINE=InnoDB');
            }
        }
        $schema->create('platform_gateway_configurations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('gateway', 30)->unique();
            $table->string('mode', 10)->default('test');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });
        $schema->create('platform_gateway_versions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('configuration_id')->constrained('platform_gateway_configurations')->restrictOnDelete();
            $table->string('mode', 10);
            $table->text('credentials');
            $table->timestamps();
            $table->index(['configuration_id', 'mode', 'id']);
        });
        $schema->create('package_payments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('tenant_database_id')->index();
            $table->unsignedBigInteger('owner_id')->index();
            $table->unsignedBigInteger('package_id');
            $table->json('quote');
            $table->string('billing_cycle', 20);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('gateway', 30);
            $table->string('mode', 10);
            $table->foreignId('configuration_version_id')->constrained('platform_gateway_versions')->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('idempotency_key', 100);
            $table->char('payload_hash', 64);
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('status', 30)->default('creating')->index();
            $table->string('failure_code', 50)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['tenant_database_id', 'idempotency_key']);
        });
        $schema->create('platform_webhook_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('configuration_version_id');
            $table->string('event_id', 100);
            $table->char('body_hash', 64);
            $table->string('event_type', 50);
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamps();
            $table->unique(['configuration_version_id', 'event_id'], 'platform_event_version_unique');
        });
        $schema->table('user_packages', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_database_id')->nullable()->index();
            $table->unsignedBigInteger('package_payment_id')->nullable()->unique();
            $table->string('mode', 10)->nullable();
            $table->json('price_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(config('subscriptions.connection'));
        $schema->table('user_packages', fn (Blueprint $table) => $table->dropColumn(['tenant_database_id', 'package_payment_id', 'mode', 'price_snapshot']));
        foreach (['platform_webhook_events', 'package_payments', 'platform_gateway_versions', 'platform_gateway_configurations'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
