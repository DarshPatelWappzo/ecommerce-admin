<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $metadata = $connection->selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$connection->getDatabaseName(), $connection->getTablePrefix() . 'categories']);
            if ($metadata && strtolower($metadata->ENGINE) !== 'innodb') {
                $connection->statement('ALTER TABLE ' . $connection->getQueryGrammar()->wrapTable('categories') . ' ENGINE=InnoDB, ROW_FORMAT=DYNAMIC');
            }
        }
        Schema::create('coupons', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('discount_type', 10)->index();
            $table->decimal('discount_value', 15, 2);
            $table->decimal('maximum_discount', 15, 2)->nullable();
            $table->decimal('minimum_subtotal', 15, 2)->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        foreach (['products' => 'product_id', 'categories' => 'category_id', 'customers' => 'customer_id'] as $tableName => $column) {
            Schema::create('coupon_' . $tableName, function (Blueprint $table) use ($tableName, $column): void {
                $table->engine('InnoDB');
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->foreignId($column)->constrained($tableName)->restrictOnDelete();
                $table->primary(['coupon_id', $column]);
            });
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('coupon_discount', 15, 2)->default(0);
            $table->json('coupon_snapshot')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('coupon_discount', 15, 2)->default(0);
        });
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['coupon_id', 'released_at', 'customer_id'], 'coupon_usage_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::table('order_items', fn(Blueprint $table) => $table->dropColumn('coupon_discount'));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'coupon_discount', 'coupon_snapshot']);
        });
        foreach (['customers', 'categories', 'products'] as $table) {
            Schema::dropIfExists('coupon_' . $table);
        }
        Schema::dropIfExists('coupons');
    }
};
