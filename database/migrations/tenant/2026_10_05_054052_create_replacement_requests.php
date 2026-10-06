<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('replacement_requests', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('replacement_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('reason_id')->constrained('return_reasons')->restrictOnDelete();
            $table->foreignId('return_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 30)->index();
            $table->text('reason_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('inventory_disposition', 30)->nullable();
            $table->boolean('stock_reserved')->default(false);
            $table->json('policy_snapshot');
            $table->timestamp('requested_at')->index();
            foreach (['approved', 'rejected', 'picked_up', 'received', 'qc_completed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'out_of_stock', 'converted'] as $action) {
                $table->timestamp($action . '_at')->nullable();
            }
            $table->timestamps();
            $table->index(['order_item_id', 'status']);
        });
        Schema::create('replacement_status_histories', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('replacement_request_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('replacement_stock_movements', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('replacement_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('event', 20);
            $table->integer('quantity_delta');
            $table->integer('reserved_delta');
            $table->unsignedInteger('quantity_after');
            $table->unsignedInteger('reserved_after');
            $table->timestamp('created_at');
            $table->unique(['replacement_request_id', 'event'], 'replacement_stock_event_unique');
        });
        Schema::table('order_shipments', function (Blueprint $table): void {
            $table->foreignId('replacement_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('shipment_key', 40)->default('original');
            $table->unique(['order_id', 'shipment_key']);
        });
        Schema::table('order_shipments', function (Blueprint $table): void {
            $table->dropUnique(['order_id']);
        });
        foreach (['view', 'create', 'approve', 'reject', 'update_status', 'qc', 'cancel'] as $action) {
            $slug = 'replacements.' . $action;
            DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => 'Replacements ' . ucfirst(str_replace('_', ' ', $action)), 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $role) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('order_shipments')->whereNotNull('replacement_request_id')->delete();
        Schema::table('order_shipments', function (Blueprint $table): void {
            $table->unique('order_id');
            $table->dropUnique(['order_id', 'shipment_key']);
            $table->dropForeign(['replacement_request_id']);
            $table->dropUnique(['replacement_request_id']);
            $table->dropColumn('replacement_request_id');
            $table->dropColumn('shipment_key');
        });
        Schema::dropIfExists('replacement_stock_movements');
        Schema::dropIfExists('replacement_status_histories');
        Schema::dropIfExists('replacement_requests');
        DB::table('permissions')->where('slug', 'like', 'replacements.%')->delete();
    }
};
