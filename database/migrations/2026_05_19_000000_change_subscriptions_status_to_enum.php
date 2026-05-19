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
        DB::statement("CREATE TYPE subscription_status AS ENUM ('pending', 'active', 'expired', 'cancelled')");

        DB::statement("
            ALTER TABLE subscriptions
            ALTER COLUMN status DROP DEFAULT,
            ALTER COLUMN status TYPE subscription_status
            USING status::subscription_status,
            ALTER COLUMN status SET DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE subscriptions
            ALTER COLUMN status DROP DEFAULT,
            ALTER COLUMN status TYPE varchar(255)
            USING status::text,
            ALTER COLUMN status SET DEFAULT 'pending'
        ");

        DB::statement('DROP TYPE subscription_status');
    }
};
