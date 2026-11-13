<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que le client declare, distinct de ce que nous verifions.
 *
 * `reference_number` reste la reference CONSTATEE par l'administrateur sur le
 * compte marchand. `payer_declared_reference` est ce que le client affirme
 * avoir recu par SMS. Les confondre reviendrait a traiter une affirmation
 * comme une preuve — exactement ce qu'on cherche a eviter en cas de litige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_declared_reference', 120)->nullable()->after('reference_number');
            $table->timestamp('payer_declared_at')->nullable()->after('payer_declared_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_declared_reference', 'payer_declared_at']);
        });
    }
};
