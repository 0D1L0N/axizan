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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('XOF');
            $table->string('payment_method');
            $table->enum('payment_provider', ['mtn', 'moov', 'celtiis', 'sandbox'])->nullable();
            $table->string('status')->default('pending');
            $table->string('transaction_id')->nullable()->unique();
            $table->string('reference_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('mobile_money_transaction_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
