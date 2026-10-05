<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('product_type', 30)->index();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->boolean('featured')->default(false)->index();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->decimal('price', 12, 2)->index();
            $table->decimal('special_price', 12, 2)->nullable();
            $table->date('special_price_from')->nullable();
            $table->date('special_price_to')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('status')->default(true);
            $table->string('combination_key', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'combination_key']);
        });
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 30);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->string('value');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
            $table->unique(['id', 'attribute_id']);
        });
        foreach (['categories' => 'category_id', 'tags' => 'tag_id'] as $target => $column) {
            Schema::create('product_'.$target, function (Blueprint $table) use ($target, $column): void {
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId($column)->constrained($target)->restrictOnDelete();
                $table->unique(['product_id', $column]);
            });
        }
        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->unsignedBigInteger('attribute_option_id')->nullable();
            $table->text('text_value')->nullable();
            $table->unique(['product_id', 'attribute_id']);
            $table->foreign(['attribute_option_id', 'attribute_id'], 'pav_option_attribute_fk')
                ->references(['id', 'attribute_id'])->on('attribute_options')->restrictOnDelete();
        });
        Schema::create('variant_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->restrictOnDelete();
            $table->unsignedBigInteger('attribute_option_id');
            $table->unique(['variant_id', 'attribute_id']);
            $table->foreign(['attribute_option_id', 'attribute_id'], 'vav_option_attribute_fk')
                ->references(['id', 'attribute_id'])->on('attribute_options')->restrictOnDelete();
        });
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->string('image');
            $table->string('alt_text')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sort_order']);
        });
        Schema::create('related_products', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->unique(['product_id', 'related_product_id']);
        });
    }

    public function down(): void
    {
        foreach (['related_products', 'product_images', 'variant_attribute_values', 'product_attribute_values',
            'product_tags', 'product_categories', 'attribute_options', 'attributes', 'tags', 'product_variants', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
