<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('role')->default('client')->after('password');
            $table->string('status')->default('active')->after('role');
            $table->string('avatar_url')->nullable()->after('status');
            $table->text('bio')->nullable()->after('avatar_url');
            $table->boolean('kyc_verified')->default(false)->after('bio');
            $table->string('kyc_document_url')->nullable()->after('kyc_verified');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'phone', 'role', 'status', 'avatar_url', 'bio', 'kyc_verified',
                'kyc_document_url', 'phone_verified_at',
            ]);
        });
    }
};
