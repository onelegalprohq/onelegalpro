<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ADR-016 Decision 4: this is the sole class-(b), pre-FirmContext relation.
        Schema::connection('pgsql_migration')->create('platform_administration_firm_registry', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('canonical_name');
            $table->text('jurisdiction_reference');
            $table->text('lifecycle_state');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });

        DB::connection('pgsql_migration')->statement("alter table platform_administration_firm_registry add constraint platform_administration_firm_registry_lifecycle_state_check check (lifecycle_state in ('Requested', 'Provisioned', 'Active', 'Closed'))");

        foreach (['DB_USERNAME' => 'select (id, lifecycle_state)', 'DB_OUTBOX_USERNAME' => 'select'] as $variable => $privilege) {
            $role = getenv($variable);

            if (! is_string($role) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $role) !== 1) {
                throw new RuntimeException('Registry database role configuration is unavailable.');
            }

            DB::connection('pgsql_migration')->statement('grant '.$privilege.' on table platform_administration_firm_registry to "'.$role.'"');
        }
    }

    public function down(): void
    {
        Schema::connection('pgsql_migration')->dropIfExists('platform_administration_firm_registry');
    }
};
