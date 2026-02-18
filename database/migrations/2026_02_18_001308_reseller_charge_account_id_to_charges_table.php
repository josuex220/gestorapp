<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a nullable column to the charges table that links a charge
     * to a reseller sub-account (user). When the charge is paid,
     * the system automatically activates/renews the sub-account.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedBigInteger('reseller_charge_account_id')
                  ->nullable()
                  ->after('client_id')
                  ->comment('Links charge to a reseller sub-account for auto-renewal on payment');

            $table->index('reseller_charge_account_id', 'idx_charges_reseller_account');
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex('idx_charges_reseller_account');
            $table->dropColumn('reseller_charge_account_id');
        });
    }
};
