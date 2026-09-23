<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->engine('InnoDB');
                $table->id();
                $table->string('customer_code', 30)->unique();
                $table->string('first_name', 100);
                $table->string('last_name', 100)->nullable();
                $table->string('email', 254)->nullable()->unique();
                $table->string('phone_country_code', 5)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('customer_type', 20)->default('individual')->index();
                $table->string('company_name')->nullable();
                $table->string('gstin', 15)->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('created_at');
                $table->index(['phone_country_code', 'phone']);
            });
        }

        $this->useTransactionalEngine('customers');
        foreach (
            [
                'customers_customer_code_unique' => [['customer_code'], 'unique'],
                'customers_email_unique' => [['email'], 'unique'],
                'customers_customer_type_index' => [['customer_type'], 'index'],
                'customers_status_index' => [['status'], 'index'],
                'customers_created_at_index' => [['created_at'], 'index'],
                'customers_phone_country_code_phone_index' => [['phone_country_code', 'phone'], 'index'],
            ] as $name => [$columns, $type]
        ) {
            if (! Schema::hasIndex('customers', $name)) {
                Schema::table('customers', function (Blueprint $table) use ($columns, $name, $type): void {
                    $table->$type($columns, $name);
                });
            }
        }

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table): void {
                $table->engine('InnoDB');
                $table->id();
                $table->foreignId('customer_id')->constrained()->restrictOnDelete();
                $table->string('label', 50)->nullable();
                $table->string('recipient_name', 200);
                $table->string('phone_country_code', 5);
                $table->string('phone', 20);
                $table->string('address_line_1');
                $table->string('address_line_2')->nullable();
                $table->string('landmark')->nullable();
                $table->string('city', 100);
                $table->string('state_code', 10);
                $table->char('country_code', 2);
                $table->string('postal_code', 20);
                $table->boolean('is_default_shipping')->default(false);
                $table->boolean('is_default_billing')->default(false);
                $table->timestamps();
            });
        }

        $this->useTransactionalEngine('customer_addresses');
        $hasCustomerForeignKey = collect(Schema::getForeignKeys('customer_addresses'))->contains(
            fn(array $key): bool => $key['columns'] === ['customer_id'] && $key['foreign_table'] === 'customers'
        );
        if (! $hasCustomerForeignKey) {
            Schema::table('customer_addresses', function (Blueprint $table): void {
                $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            });
        }
    }

    /** Recover MySQL's non-transactional partial DDL without dropping existing rows. */
    private function useTransactionalEngine(string $table): void
    {
        $connection = Schema::getConnection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $metadata = $connection->selectOne(
            'SELECT ENGINE, ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$connection->getDatabaseName(), $connection->getTablePrefix() . $table]
        );
        if (strtolower($metadata->ENGINE) !== 'innodb' || strtolower($metadata->ROW_FORMAT) !== 'dynamic') {
            $name = $connection->getQueryGrammar()->wrapTable($table);
            $connection->statement('ALTER TABLE ' . $name . ' ENGINE=InnoDB, ROW_FORMAT=DYNAMIC');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
