<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\PlatformAdministration;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FirmRegistrySchemaTest extends TestCase
{
    private const TABLE = 'platform_administration_firm_registry';

    public function test_registry_relation_has_only_the_approved_structure_and_no_row_security(): void
    {
        if (! $this->postgreSqlRequired()) {
            return;
        }

        $database = DB::connection('pgsql_migration');
        $columns = $database->select(
            'select column_name, data_type, is_nullable, column_default from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position',
            ['public', self::TABLE],
        );

        $this->assertSame(
            [
                ['id', 'uuid', 'NO', null],
                ['canonical_name', 'text', 'NO', null],
                ['jurisdiction_reference', 'text', 'NO', null],
                ['lifecycle_state', 'text', 'NO', null],
                ['created_at', 'timestamp with time zone', 'NO', null],
                ['updated_at', 'timestamp with time zone', 'NO', null],
            ],
            array_map(static fn (object $column): array => [(string) $column->column_name, (string) $column->data_type, (string) $column->is_nullable, $column->column_default], $columns),
        );

        $constraints = $database->select(
            'select contype, pg_get_constraintdef(oid) as definition from pg_constraint where conrelid = ?::regclass order by contype, conname',
            ['public.'.self::TABLE],
        );
        $this->assertSame(['c', 'p'], array_map(static fn (object $constraint): string => (string) $constraint->contype, $constraints));
        $this->assertStringContainsString("lifecycle_state = ANY (ARRAY['Requested'::text, 'Provisioned'::text, 'Active'::text, 'Closed'::text])", (string) $constraints[0]->definition);
        $this->assertSame(0, (int) $database->scalar('select count(*) from pg_constraint where conrelid = ?::regclass and contype = ?', ['public.'.self::TABLE, 'f']));
        $indexes = $database->select('select indexname, indexdef from pg_indexes where schemaname = ? and tablename = ? order by indexname', ['public', self::TABLE]);
        $this->assertCount(1, $indexes);
        $this->assertSame(self::TABLE.'_pkey', (string) $indexes[0]->indexname);
        $this->assertStringContainsString('UNIQUE INDEX', (string) $indexes[0]->indexdef);
        $this->assertStringContainsString(' (id)', (string) $indexes[0]->indexdef);
        $this->assertSame(0, (int) $database->scalar('select count(*) from pg_policies where schemaname = ? and tablename = ?', ['public', self::TABLE]));
        $this->assertFalse((bool) $database->scalar('select relrowsecurity or relforcerowsecurity from pg_class where oid = ?::regclass', ['public.'.self::TABLE]));
    }

    private function postgreSqlRequired(): bool
    {
        if (! filter_var(env('REQUIRE_POSTGRESQL_TEST_DATABASE', false), FILTER_VALIDATE_BOOL)) {
            $this->addToAssertionCount(1);

            return false;
        }

        return true;
    }
}
