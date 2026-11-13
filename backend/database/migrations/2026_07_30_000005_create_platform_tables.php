<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique();
            $table->string('role')->default('client')->index();
            $table->string('status')->default('active')->index();
            $table->string('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('kyc_verified')->default(false);
            $table->string('kyc_document_url')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('bounds_ne_lat', 10, 8)->nullable();
            $table->decimal('bounds_ne_lon', 11, 8)->nullable();
            $table->decimal('bounds_sw_lat', 10, 8)->nullable();
            $table->decimal('bounds_sw_lon', 11, 8)->nullable();
            $table->decimal('base_delivery_fee', 12, 2)->default(0);
            $table->decimal('extra_fee_per_km', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('delivery_zones')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable()->index();
            $table->time('time_start');
            $table->time('time_end');
            $table->string('display_label', 50)->nullable();
            $table->unsignedInteger('max_orders_per_slot')->default(50);
            $table->unsignedInteger('current_orders_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
            $table->index(['zone_id', 'day_of_week']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('quartier');
            $table->string('rue')->nullable();
            $table->string('numero_rue', 50)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('detailed_instructions')->nullable();
            $table->string('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_price', 12, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discounted_price', 12, 2)->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedInteger('daily_sold')->default(0);
            $table->string('image_url')->nullable();
            $table->unsignedSmallInteger('preparation_time_minutes')->default(30);
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->string('quantity_unit', 20)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unique(['menu_id', 'product_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('discount_type', 30)->default('percentage');
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->unsignedInteger('max_usage_total')->nullable();
            $table->unsignedInteger('max_usage_per_user')->default(1);
            $table->unsignedInteger('usage_count')->default(0);
            $table->string('applicable_to', 30)->default('all');
            $table->json('applicable_categories')->nullable();
            $table->json('applicable_products')->nullable();
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'start_date', 'end_date']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('preparateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('livreur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_address_id')->constrained('addresses')->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('delivery_slot_id')->nullable()->constrained('delivery_slots')->nullOnDelete();
            $table->string('delivery_slot_time', 50)->nullable();
            $table->date('delivery_date')->nullable()->index();
            $table->timestamp('estimated_delivery_time')->nullable();
            $table->timestamp('actual_delivery_time')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->foreignId('promo_code_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->decimal('promo_discount', 12, 2)->default(0);
            $table->string('payment_method', 30)->default('mobile_money');
            $table->string('payment_status', 30)->default('pending')->index();
            $table->string('payment_transaction_id')->nullable();
            $table->text('client_notes')->nullable();
            $table->text('preparateur_notes')->nullable();
            $table->text('livreur_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('preparing_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivery_started_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'created_at']);
            $table->index(['preparateur_id', 'delivery_date']);
            $table->index(['livreur_id', 'delivery_date']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->string('quantity_unit', 20)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->text('special_instructions')->nullable();
            $table->json('removed_ingredients')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('livreur_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('assigned')->index();
            $table->timestamp('pickup_time')->nullable();
            $table->timestamp('delivery_time')->nullable();
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->string('proof_photo_url')->nullable();
            $table->text('signature_data')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->unsignedTinyInteger('client_rating')->nullable();
            $table->text('client_feedback')->nullable();
            $table->timestamps();
            $table->index(['livreur_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('XOF');
            $table->string('payment_method', 30);
            $table->string('payment_provider', 50)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('reference_number')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('mobile_money_transaction_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['promotion_id', 'order_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_phone', 20)->nullable();
            $table->string('client_email')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('delivery_fee', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('is_sent_sms')->default(false);
            $table->boolean('is_sent_email')->default(false);
            $table->boolean('is_sent_in_app')->default(false);
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 30)->default('overall');
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_verified_purchase')->default(true);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->index(['reviewee_id', 'rating']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('delivery_slots');
        Schema::dropIfExists('delivery_zones');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->dropColumn([
                'phone', 'role', 'status', 'avatar_url', 'bio', 'kyc_verified',
                'kyc_document_url', 'phone_verified_at',
            ]);
        });
    }
};
