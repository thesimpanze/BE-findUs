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
        DB::statement("CREATE TYPE subscription_plan_name AS ENUM ('free', 'premium')");

        DB::statement("
            ALTER TABLE subscriptions
            ALTER COLUMN plan_name SET DEFAULT 'free',
            ALTER COLUMN plan_name TYPE subscription_plan_name
            USING COALESCE(plan_name, 'free')::subscription_plan_name,
            ALTER COLUMN plan_name SET NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE subscriptions
            ALTER COLUMN plan_name DROP DEFAULT,
            ALTER COLUMN plan_name DROP NOT NULL,
            ALTER COLUMN plan_name TYPE varchar(255)
            USING plan_name::text
        ");

        DB::statement('DROP TYPE subscription_plan_name');
    }
};
