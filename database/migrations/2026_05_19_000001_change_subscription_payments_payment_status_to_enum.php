<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            CREATE TYPE payment_status AS ENUM (
                'pending',
                'paid',
                'failed',
                'expired',
                'cancelled'
            )
        ");

        DB::statement("
            ALTER TABLE subscription_payments
            ALTER COLUMN payment_status DROP DEFAULT,
            ALTER COLUMN payment_status TYPE payment_status
            USING payment_status::payment_status,
            ALTER COLUMN payment_status SET DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE subscription_payments
            ALTER COLUMN payment_status DROP DEFAULT,
            ALTER COLUMN payment_status TYPE varchar(255)
            USING payment_status::text,
            ALTER COLUMN payment_status SET DEFAULT 'pending'
        ");

        DB::statement("DROP TYPE payment_status");
    }
};
