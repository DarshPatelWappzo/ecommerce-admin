<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->boolean('is_returnable')->nullable();
            $table->unsignedSmallInteger('return_days')->nullable();
            $table->boolean('is_replaceable')->nullable();
            $table->unsignedSmallInteger('replacement_days')->nullable();
        });
        Schema::create('return_reasons', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('name', 150);
            $table->boolean('status')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        foreach (['Damaged product', 'Wrong product received', 'Product not as described', 'Defective product', 'Missing parts/accessories', 'Size/fit issue', 'Changed mind', 'Other'] as $sort => $name) {
            DB::table('return_reasons')->insert(['name' => $name, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('return_requests', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('return_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('reason_id')->constrained('return_reasons')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('reason_note')->nullable();
            $table->string('status', 30)->index();
            $table->string('inventory_disposition', 30)->nullable();
            $table->decimal('refund_amount', 15, 2);
            $table->json('calculation');
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('requested_at')->index();
            foreach (['approved', 'rejected', 'received', 'inspected', 'completed'] as $action) {
                $table->timestamp($action . '_at')->nullable();
                $table->foreignId($action . '_by')->nullable()->constrained('users')->nullOnDelete();
            }
            $table->timestamps();
            $table->index(['order_item_id', 'status']);
        });
        Schema::create('refunds', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('refund_number', 40)->unique();
            $table->foreignId('return_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('order_payments')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('payment_method', 30);
            $table->string('gateway', 30)->nullable();
            $table->string('status', 20)->index();
            $table->string('gateway_refund_id', 100)->nullable()->unique();
            $table->string('manual_method', 30)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('admin_note')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('refund_attempts', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('refund_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('gateway_refund_id', 100)->nullable()->unique();
            $table->string('status', 20);
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('return_stock_movements', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('return_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_delta');
            $table->unsignedInteger('quantity_after');
            $table->timestamp('created_at');
        });
        Schema::create('customer_login_codes', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        foreach (['returns.view', 'returns.create', 'returns.review', 'returns.receive', 'returns.inspect', 'returns.close', 'returns.reasons', 'refunds.initiate', 'refunds.retry', 'refunds.reconcile', 'refunds.manual'] as $slug) {
            DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => ucwords(str_replace('.', ' ', $slug)), 'created_at' => now(), 'updated_at' => now()]);
            $permission = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $role) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
            }
        }
    }

    public function down(): void
    {
        foreach (['customer_login_codes', 'return_stock_movements', 'refund_attempts', 'refunds', 'return_requests', 'return_reasons'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('order_items', fn(Blueprint $table) => $table->dropColumn(['is_returnable', 'return_days', 'is_replaceable', 'replacement_days']));
        $ids = DB::table('permissions')->where('slug', 'like', 'returns.%')->orWhere('slug', 'like', 'refunds.%')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
