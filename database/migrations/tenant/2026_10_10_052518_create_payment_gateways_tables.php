<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table): void {
                $table->engine('InnoDB');
                $table->id();
                $table->string('gateway', 50)->unique();
                $table->string('mode', 10)->default('test');
                $table->text('test_credentials')->nullable();
                $table->text('live_credentials')->nullable();
                $table->json('settings')->nullable();
                $table->boolean('is_enabled')->default(false);
                $table->timestamps();
            });
        }
        $this->useTransactionalEngine('payment_gateways');

        if (! Schema::hasTable('payment_gateway_credentials')) {
            Schema::create('payment_gateway_credentials', function (Blueprint $table): void {
                $table->engine('InnoDB');
                $table->id();
                $table->foreignId('payment_gateway_id')->constrained()->restrictOnDelete();
                $table->string('mode', 10);
                $table->string('key_id', 100)->nullable()->index();
                $table->text('credentials')->nullable();
                $table->boolean('is_current')->default(true);
                $table->timestamps();
                $table->index(['payment_gateway_id', 'mode', 'is_current'], 'gateway_mode_current');
            });
        }
        $this->useTransactionalEngine('payment_gateway_credentials');
        $this->ensureForeignKey('payment_gateway_credentials', 'payment_gateway_id', 'payment_gateways');

        foreach (['order_payment_checkouts', 'order_payments'] as $name) {
            $this->useTransactionalEngine($name);
            if (! Schema::hasColumn($name, 'payment_gateway_credential_id')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->foreignId('payment_gateway_credential_id')->nullable();
                });
            }
            $this->ensureForeignKey($name, 'payment_gateway_credential_id', 'payment_gateway_credentials');
        }
    }

    /** Resume partially committed MySQL DDL without dropping existing payment or credential rows. */
    private function useTransactionalEngine(string $table): void
    {
        $connection = Schema::getConnection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $metadata = $connection->selectOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$connection->getDatabaseName(), $connection->getTablePrefix().$table]
        );
        if (strtolower($metadata->ENGINE) !== 'innodb') {
            $connection->statement('ALTER TABLE '.$connection->getQueryGrammar()->wrapTable($table).' ENGINE=InnoDB');
        }
    }

    private function ensureForeignKey(string $table, string $column, string $parent): void
    {
        $exists = collect(Schema::getForeignKeys($table))->contains(
            fn (array $key): bool => $key['columns'] === [$column] && $key['foreign_table'] === $parent
        );
        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parent): void {
                $blueprint->foreign($column)->references('id')->on($parent)->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['order_payments', 'order_payment_checkouts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payment_gateway_credential_id');
            });
        }
        Schema::dropIfExists('payment_gateway_credentials');
        Schema::dropIfExists('payment_gateways');
    }
};
