<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique();
            $table->decimal('bounds_ne_lat', 10, 7)->nullable(); $table->decimal('bounds_ne_lon', 10, 7)->nullable();
            $table->decimal('bounds_sw_lat', 10, 7)->nullable(); $table->decimal('bounds_sw_lon', 10, 7)->nullable();
            $table->decimal('base_delivery_fee', 10, 2)->default(500); $table->decimal('extra_fee_per_km', 10, 2)->default(0);
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id(); $table->foreignId('zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable(); $table->time('time_start'); $table->time('time_end');
            $table->string('display_label'); $table->unsignedInteger('max_orders_per_slot')->default(20); $table->unsignedInteger('current_orders_count')->default(0);
            $table->boolean('is_active')->default(true); $table->unsignedInteger('priority')->default(0); $table->timestamps();
        });
        Schema::create('addresses', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('label');
            $table->string('quartier'); $table->string('rue')->nullable(); $table->string('numero_rue')->nullable();
            $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 10, 7)->nullable();
            $table->text('detailed_instructions')->nullable(); $table->string('landmark')->nullable();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->boolean('is_default')->default(false); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->string('order_number')->unique();
            $table->foreignId('client_id')->constrained('users'); $table->foreignId('preparateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('livreur_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('delivery_address_id')->constrained('addresses');
            $table->foreignId('delivery_slot_id')->nullable()->constrained('delivery_slots')->nullOnDelete(); $table->string('status')->default('pending');
            $table->date('delivery_date'); $table->decimal('subtotal', 12, 2); $table->decimal('delivery_fee', 12, 2)->default(0); $table->decimal('tax', 12, 2)->default(0); $table->decimal('discount_amount', 12, 2)->default(0); $table->decimal('total', 12, 2);
            $table->string('payment_method')->default('cash'); $table->string('payment_status')->default('pending'); $table->text('client_notes')->nullable(); $table->text('preparateur_notes')->nullable(); $table->text('livreur_notes')->nullable(); $table->text('cancellation_reason')->nullable();
            $table->timestamp('accepted_at')->nullable(); $table->timestamp('preparing_started_at')->nullable(); $table->timestamp('ready_at')->nullable(); $table->timestamp('delivery_started_at')->nullable(); $table->timestamp('delivered_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 10, 3); $table->string('quantity_unit')->default('piece'); $table->decimal('unit_price', 12, 2); $table->decimal('total_price', 12, 2); $table->text('special_instructions')->nullable(); $table->json('removed_ingredients')->nullable();
            // Achat au marche : que faire si l'article est introuvable (replace|remove).
            $table->string('substitution_preference')->default('replace'); $table->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete(); $table->foreignId('livreur_id')->constrained('users'); $table->string('status')->default('assigned'); $table->timestamp('pickup_time')->nullable(); $table->timestamp('delivery_time')->nullable(); $table->decimal('current_latitude', 10, 7)->nullable(); $table->decimal('current_longitude', 10, 7)->nullable(); $table->timestamp('last_location_update')->nullable(); $table->string('proof_photo_url')->nullable(); $table->text('signature_data')->nullable(); $table->text('delivery_notes')->nullable(); $table->unsignedTinyInteger('client_rating')->nullable(); $table->text('client_feedback')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('deliveries'); Schema::dropIfExists('order_items'); Schema::dropIfExists('orders'); Schema::dropIfExists('addresses'); Schema::dropIfExists('delivery_slots'); Schema::dropIfExists('delivery_zones'); }
};
