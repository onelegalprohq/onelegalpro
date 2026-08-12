<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Tenancy\FirmContext;
use App\Infrastructure\Database\FirmTransactionManager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * PF-073 — the focused suite for the Firm transaction boundary.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly, boots no Laravel
 * application, opens no database connection, and uses a controlled `Connection`
 * double. `Tests\TestCase`, `RefreshDatabase`, `DatabaseTransactions`, and every
 * other ambient-transaction trait are prohibited here, because the contract
 * requires every manager call to start at transaction level zero — a suite that
 * wrapped its tests in a transaction would test the opposite of the contract.
 *
 * The concrete identifier leaves at the bottom of this file are test fixtures
 * only and carry no business meaning.
 */
final class FirmTransactionManagerTest extends TestCase
{
    private const FIRM_ID = '019fa14f-813e-702f-aa24-5b85bd74d75f';

    private const OTHER_FIRM_ID = '019fb2c8-5d41-7a02-9c3e-6f0c4a1b83d2';

    private const ACTOR_ID = '019fa154-ef35-73b7-99c9-7b422ec3172b';

    private const CORRELATION_ID = '019fa15b-252c-7216-8f16-1af932c30b4e';

    private const SOURCE_PATH = '/app/Infrastructure/Database/FirmTransactionManager.php';

    /** The journal a completely successful run must produce, in order. */
    private const SUCCESSFUL_ORDER = [
        'transactionLevel',
        'scalar',           // superuser probe
        'scalar',           // BYPASSRLS probe
        'scalar',           // pre-existing setting probe
        'beginTransaction',
        'scalar',           // set_config(..., true)
        'callback',
        'scalar',           // post-callback setting probe
        'commit',
        'scalar',           // post-commit cleanup probe
    ];

    // ---------------------------------------------------------------- shape

    public function test_it_is_a_final_class_at_the_approved_path_and_namespace(): void
    {
        $reflection = new \ReflectionClass(FirmTransactionManager::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isInterface());
        $this->assertSame('App\\Infrastructure\\Database', $reflection->getNamespaceName());
        $this->assertSame($this->sourcePath(), $reflection->getFileName());
        $this->assertFalse($reflection->getParentClass());
        $this->assertSame([], $reflection->getInterfaceNames());
    }

    public function test_the_constructor_receives_exactly_one_concrete_connection(): void
    {
        $constructor = (new \ReflectionClass(FirmTransactionManager::class))->getConstructor();

        $this->assertInstanceOf(\ReflectionMethod::class, $constructor);
        $this->assertTrue($constructor->isPublic());
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());

        $parameter = $constructor->getParameters()[0];
        $type = $parameter->getType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame(Connection::class, $type->getName());
        $this->assertFalse($type->allowsNull());
        $this->assertFalse($parameter->isDefaultValueAvailable());
        $this->assertFalse($parameter->isVariadic());
    }

    public function test_it_declares_exactly_the_canonical_firm_setting_constant(): void
    {
        $reflection = new \ReflectionClass(FirmTransactionManager::class);

        $this->assertSame('onelegalpro.firm_id', FirmTransactionManager::FIRM_SETTING);

        $public = array_values(array_filter(
            $reflection->getReflectionConstants(),
            static fn (\ReflectionClassConstant $constant): bool => $constant->isPublic(),
        ));

        $this->assertCount(1, $public);
        $this->assertSame('FIRM_SETTING', $public[0]->getName());

        foreach ($reflection->getReflectionConstants() as $constant) {
            $this->assertFalse($constant->isProtected(), $constant->getName().' must not be protected.');
        }
    }

    public function test_it_exposes_exactly_one_public_operation_and_no_other_api(): void
    {
        $reflection = new \ReflectionClass(FirmTransactionManager::class);

        $this->assertSame(
            ['__construct', 'run'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            ),
        );

        $this->assertSame([], $reflection->getMethods(\ReflectionMethod::IS_PROTECTED));
        $this->assertSame([], $reflection->getMethods(\ReflectionMethod::IS_STATIC));

        foreach ($reflection->getMethods() as $method) {
            $this->assertFalse($method->isStatic(), $method->getName().' must not be static.');
        }
    }

    public function test_run_declares_the_exact_approved_signature(): void
    {
        $run = new \ReflectionMethod(FirmTransactionManager::class, 'run');
        $returnType = $run->getReturnType();

        $this->assertTrue($run->isPublic());
        $this->assertFalse($run->isStatic());
        $this->assertSame(2, $run->getNumberOfParameters());
        $this->assertSame(2, $run->getNumberOfRequiredParameters());
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('mixed', $returnType->getName());

        [$context, $callback] = $run->getParameters();

        $contextType = $context->getType();
        $callbackType = $callback->getType();

        $this->assertSame('context', $context->getName());
        $this->assertInstanceOf(\ReflectionNamedType::class, $contextType);
        $this->assertSame(FirmContext::class, $contextType->getName());

        $this->assertSame('callback', $callback->getName());
        $this->assertInstanceOf(\ReflectionNamedType::class, $callbackType);
        $this->assertSame('callable', $callbackType->getName());
    }

    public function test_run_declares_the_exact_phpstan_generic_contract(): void
    {
        $docBlock = (new \ReflectionMethod(FirmTransactionManager::class, 'run'))->getDocComment();

        $this->assertIsString($docBlock);
        $this->assertStringContainsString('@template TResult', $docBlock);
        $this->assertStringContainsString(
            '@param  callable(Connection): TResult  $callback',
            $docBlock,
        );
        $this->assertStringContainsString('@return TResult', $docBlock);
    }

    // ------------------------------------------------------------- happy path

    public function test_a_successful_run_follows_the_approved_order(): void
    {
        $connection = $this->readyConnection();
        $manager = new FirmTransactionManager($connection);

        $manager->run($this->context(), static function (Connection $managed): string {
            $managed->note('callback');

            return 'done';
        });

        $this->assertSame(self::SUCCESSFUL_ORDER, $connection->journal);
        $this->assertSame(0, $connection->unexpectedScalarCalls);
        $this->assertSame(0, $connection->disconnects);
    }

    public function test_the_callback_receives_the_exact_managed_connection(): void
    {
        $connection = $this->readyConnection();
        $received = null;

        (new FirmTransactionManager($connection))->run(
            $this->context(),
            static function (Connection $managed) use (&$received): bool {
                $managed->note('callback');
                $received = $managed;

                return true;
            },
        );

        $this->assertSame($connection, $received);
    }

    public function test_the_exact_callback_result_is_returned_after_commit(): void
    {
        $connection = $this->readyConnection();
        $result = new \stdClass;

        $returned = (new FirmTransactionManager($connection))->run(
            $this->context(),
            static function (Connection $managed) use ($result): \stdClass {
                $managed->note('callback');

                return $result;
            },
        );

        $this->assertSame($result, $returned);
        $this->assertContains('commit', $connection->journal);
    }

    public function test_a_read_callback_receives_the_same_boundary_as_a_write(): void
    {
        $connection = $this->readyConnection();

        $returned = (new FirmTransactionManager($connection))->run(
            $this->context(),
            static function (Connection $managed): string {
                $managed->note('callback');

                return 'read-result';
            },
        );

        $this->assertSame('read-result', $returned);
        $this->assertSame(self::SUCCESSFUL_ORDER, $connection->journal);
    }

    // ------------------------------------------------------------------- SQL

    public function test_every_sql_call_uses_the_write_pdo_with_bound_parameters(): void
    {
        $connection = $this->readyConnection();

        (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());

        $this->assertCount(6, $connection->scalarCalls);

        foreach ($connection->scalarCalls as $index => $call) {
            $this->assertFalse(
                $call['useReadPdo'],
                'SQL call '.$index.' must pass false as $useReadPdo so it uses the write PDO.',
            );
            $this->assertNotSame([], $call['bindings'], 'SQL call '.$index.' must bind its parameters.');
            $this->assertGreaterThanOrEqual(
                1,
                substr_count($call['query'], '?'),
                'SQL call '.$index.' must carry a bound placeholder.',
            );
        }

        $this->assertSame(
            'select case when rolsuper then ? else ? end from pg_roles where rolname = current_user',
            $connection->scalarCalls[0]['query'],
        );
        $this->assertSame(
            'select case when rolbypassrls then ? else ? end from pg_roles where rolname = current_user',
            $connection->scalarCalls[1]['query'],
        );
        $this->assertSame(['privileged', 'unprivileged'], $connection->scalarCalls[0]['bindings']);
        $this->assertSame(['privileged', 'unprivileged'], $connection->scalarCalls[1]['bindings']);
    }

    public function test_the_setting_is_established_transaction_locally_and_verified(): void
    {
        $connection = $this->readyConnection();

        (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());

        $establish = $connection->scalarCalls[3];

        $this->assertSame('select set_config(?, ?, true)', $establish['query']);
        $this->assertSame([FirmTransactionManager::FIRM_SETTING, self::FIRM_ID], $establish['bindings']);

        foreach ([2, 4, 5] as $probeIndex) {
            $this->assertSame(
                "select nullif(current_setting(?, true), '')",
                $connection->scalarCalls[$probeIndex]['query'],
            );
            $this->assertSame(
                [FirmTransactionManager::FIRM_SETTING],
                $connection->scalarCalls[$probeIndex]['bindings'],
            );
        }
    }

    public function test_the_firm_identifier_is_never_interpolated_into_sql(): void
    {
        $connection = $this->readyConnection();

        (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());

        foreach ($connection->scalarCalls as $call) {
            $this->assertStringNotContainsString(self::FIRM_ID, $call['query']);
            $this->assertStringNotContainsString('onelegalpro.firm_id', $call['query']);
        }
    }

    public function test_no_statement_uses_session_level_state_or_a_reset(): void
    {
        $connection = $this->readyConnection();

        (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());

        foreach ($connection->scalarCalls as $call) {
            $query = strtolower($call['query']);

            $this->assertDoesNotMatchRegularExpression('/\bset\s+(local|session)\b/', $query);
            $this->assertStringNotContainsString('reset', $query);
            $this->assertStringNotContainsString('set_config(?, ?, false)', $query);
            $this->assertStringNotContainsString(', false)', $query);
        }
    }

    // -------------------------------------------------------------- preflight

    public function test_a_superuser_connection_is_disconnected_and_rejected_before_the_callback(): void
    {
        $connection = (new RecordingConnection)->queueScalar('privileged');

        $this->assertRejectedBeforeCallback(
            $connection,
            \RuntimeException::class,
            'is a superuser',
        );
        $this->assertSame(1, $connection->disconnects);
        $this->assertNotContains('beginTransaction', $connection->journal);
    }

    public function test_a_bypassrls_connection_is_disconnected_and_rejected_before_the_callback(): void
    {
        $connection = (new RecordingConnection)->queueScalar('unprivileged', 'privileged');

        $this->assertRejectedBeforeCallback($connection, \RuntimeException::class, 'BYPASSRLS');
        $this->assertSame(1, $connection->disconnects);
        $this->assertNotContains('beginTransaction', $connection->journal);
    }

    public function test_an_indeterminate_role_verdict_fails_closed(): void
    {
        $connection = (new RecordingConnection)->queueScalar(null);

        $this->assertRejectedBeforeCallback($connection, \RuntimeException::class, 'indeterminate');
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_failing_role_probe_fails_closed(): void
    {
        $connection = (new RecordingConnection)->queueScalar(new \RuntimeException('server closed the connection'));

        $this->assertRejectedBeforeCallback($connection, \RuntimeException::class, 'indeterminate');
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_leaked_session_setting_is_detected_disconnected_and_never_reused(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            self::OTHER_FIRM_ID,
        );

        $this->assertRejectedBeforeCallback(
            $connection,
            \RuntimeException::class,
            'already carried a Firm setting',
        );

        $this->assertSame(1, $connection->disconnects);
        $this->assertNotContains('beginTransaction', $connection->journal);
        $this->assertNotContains('rollBack', $connection->journal);

        foreach ($connection->scalarCalls as $call) {
            $this->assertStringNotContainsString('set_config', $call['query']);
        }
    }

    public function test_contamination_is_never_normalized_with_a_reset_or_session_write(): void
    {
        $connection = (new RecordingConnection)->queueScalar('unprivileged', 'unprivileged', self::OTHER_FIRM_ID);

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->failingCallback());
        } catch (\RuntimeException) {
            // asserted elsewhere; this test is about what was never issued
        }

        $this->assertCount(3, $connection->scalarCalls);

        foreach ($connection->scalarCalls as $call) {
            $query = strtolower($call['query']);

            $this->assertStringNotContainsString('reset', $query);
            $this->assertStringNotContainsString('set_config', $query);
        }
    }

    // ---------------------------------------------------------------- nesting

    public function test_an_ambient_transaction_is_rejected_before_any_statement(): void
    {
        $connection = new RecordingConnection(level: 1);

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->failingCallback());
            $this->fail('An ambient transaction must be rejected.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('transaction level zero', $exception->getMessage());
            $this->assertStringContainsString('no nesting, joining, savepoint, or context switch', strtolower($exception->getMessage()));
        }

        $this->assertSame(['transactionLevel'], $connection->journal);
        $this->assertSame([], $connection->scalarCalls);
        $this->assertSame(0, $connection->disconnects);
    }

    public function test_a_nested_manager_entry_is_rejected_whichever_firm_it_would_use(): void
    {
        foreach ([self::FIRM_ID, self::OTHER_FIRM_ID] as $firmId) {
            $connection = new RecordingConnection(level: 2);

            $this->expectExceptionOnRun(
                $connection,
                $this->context($firmId),
                \LogicException::class,
                'transaction level zero',
            );

            $this->assertSame(['transactionLevel'], $connection->journal);
            $this->assertSame([], $connection->scalarCalls);
        }
    }

    // ------------------------------------------------------- setting failures

    public function test_a_begin_transaction_failure_is_quarantined_and_reports_no_commit(): void
    {
        $failure = new \RuntimeException('begin failed');
        $connection = (new RecordingConnection)
            ->queueScalar('unprivileged', 'unprivileged', null)
            ->failBeginWith($failure);

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('A begin-transaction failure must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not begin', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
            $this->assertSame($failure, $exception->getPrevious());
        }

        $this->assertNotContains('callback', $connection->journal);
        $this->assertNotContains('commit', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_silent_reconnect_during_begin_is_rejected_before_callback_work(): void
    {
        $connection = (new RecordingConnection)
            ->queueScalar('unprivileged', 'unprivileged', null)
            ->changePdoOnBegin();

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('A transaction on an unverified replacement connection must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('connection changed', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
        }

        $this->assertContains('rollBack', $connection->journal);
        $this->assertNotContains('callback', $connection->journal);
        $this->assertNotContains('commit', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_setting_establishment_mismatch_rolls_back_before_the_callback(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::OTHER_FIRM_ID,
        );

        $this->assertRejectedBeforeCallback(
            $connection,
            \RuntimeException::class,
            'could not be established',
        );

        $this->assertContains('beginTransaction', $connection->journal);
        $this->assertContains('rollBack', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
        $this->assertStringContainsString(
            'did not commit',
            $this->messageOf($connection, $this->context()),
        );
    }

    public function test_a_failing_setting_statement_rolls_back_before_the_callback(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            new \RuntimeException('set_config failed'),
        );

        $this->assertRejectedBeforeCallback($connection, \RuntimeException::class, 'could not be established');
        $this->assertContains('rollBack', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_rollback_failure_while_abandoning_a_setting_failure_is_not_hidden(): void
    {
        $settingFailure = new \RuntimeException('set_config failed');
        $rollbackFailure = new \RuntimeException('rollback failed');
        $connection = (new RecordingConnection)
            ->queueScalar('unprivileged', 'unprivileged', null, $settingFailure)
            ->failRollBackWith($rollbackFailure);

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('A rollback failure must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback failed', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
            $this->assertSame($settingFailure, $exception->getPrevious());
        }

        $this->assertNotContains('callback', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_changed_setting_after_the_callback_is_rejected_before_commit(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            self::OTHER_FIRM_ID,
        );

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('A changed Firm setting must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no longer matched the supplied Firm', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
        }

        $this->assertContains('callback', $connection->journal);
        $this->assertContains('rollBack', $connection->journal);
        $this->assertNotContains('commit', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_an_unverifiable_setting_after_the_callback_is_rejected_before_commit(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            new \RuntimeException('probe failed'),
        );

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('An unverifiable Firm setting must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not be confirmed unchanged', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
        }

        $this->assertContains('callback', $connection->journal);
        $this->assertContains('rollBack', $connection->journal);
        $this->assertNotContains('commit', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    // ------------------------------------------------------- failure semantics

    public function test_a_callback_failure_rethrows_the_exact_original_after_rollback_and_cleanup(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            null,
        );

        $original = new \DomainException('callback failed');

        try {
            (new FirmTransactionManager($connection))->run(
                $this->context(),
                static function (Connection $managed) use ($original): never {
                    $managed->note('callback');

                    throw $original;
                },
            );
            $this->fail('The original callback failure must propagate.');
        } catch (\Throwable $caught) {
            $this->assertSame($original, $caught);
            $this->assertNull($caught->getPrevious());
        }

        $this->assertSame(
            ['transactionLevel', 'scalar', 'scalar', 'scalar', 'beginTransaction', 'scalar', 'callback', 'rollBack', 'scalar'],
            $connection->journal,
        );
        $this->assertSame(0, $connection->disconnects);
    }

    public function test_a_rollback_failure_reports_no_commit_and_chains_the_original(): void
    {
        $connection = (new RecordingConnection)
            ->queueScalar('unprivileged', 'unprivileged', null, self::FIRM_ID)
            ->failRollBackWith(new \RuntimeException('rollback failed'));

        $original = new \DomainException('callback failed');

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->throwingCallback($original));
            $this->fail('A rollback failure must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertNotSame($original, $exception);
            $this->assertSame($original, $exception->getPrevious());
            $this->assertStringContainsString('Rollback failed', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
            $this->assertStringNotContainsString('committed durably', $exception->getMessage());
        }

        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_cleanup_failure_after_rollback_reports_no_commit_and_chains_the_original(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            self::FIRM_ID,
        );

        $original = new \DomainException('callback failed');

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->throwingCallback($original));
            $this->fail('An unverified cleanup must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($original, $exception->getPrevious());
            $this->assertStringContainsString('cleanup could not be verified after rollback', $exception->getMessage());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
            $this->assertStringNotContainsString('committed durably', $exception->getMessage());
        }

        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_commit_failure_reports_that_the_transaction_did_not_commit(): void
    {
        $commitFailure = new \RuntimeException('commit failed');

        $connection = (new RecordingConnection)
            ->queueScalar('unprivileged', 'unprivileged', null, self::FIRM_ID, self::FIRM_ID)
            ->failCommitWith($commitFailure);

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('A commit failure must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($commitFailure, $exception->getPrevious());
            $this->assertStringContainsString('did not commit', $exception->getMessage());
            $this->assertStringNotContainsString('committed durably', $exception->getMessage());
        }

        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_residual_setting_after_commit_reports_a_durable_commit(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            self::FIRM_ID,
            self::FIRM_ID,
        );

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('An unverified post-commit cleanup must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'committed durably, but connection cleanup could not be verified',
                $exception->getMessage(),
            );
            $this->assertStringContainsString('Do not retry', $exception->getMessage());
            $this->assertStringNotContainsString('did not commit', $exception->getMessage());
            $this->assertInstanceOf(\Throwable::class, $exception->getPrevious());
        }

        $this->assertContains('commit', $connection->journal);
        $this->assertSame(1, $connection->disconnects);
    }

    public function test_a_failing_cleanup_probe_after_commit_reports_a_durable_commit(): void
    {
        $probeFailure = new \RuntimeException('probe failed');

        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            self::FIRM_ID,
            $probeFailure,
        );

        try {
            (new FirmTransactionManager($connection))->run($this->context(), $this->notingCallback());
            $this->fail('An unverified post-commit cleanup must be surfaced.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($probeFailure, $exception->getPrevious());
            $this->assertStringContainsString(
                'committed durably, but connection cleanup could not be verified',
                $exception->getMessage(),
            );
        }

        $this->assertSame(1, $connection->disconnects);
    }

    public function test_no_failure_path_retries_anything(): void
    {
        $connection = (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            null,
        );

        $invocations = 0;

        try {
            (new FirmTransactionManager($connection))->run(
                $this->context(),
                static function (Connection $managed) use (&$invocations): never {
                    $managed->note('callback');
                    $invocations++;

                    throw new \DomainException('callback failed');
                },
            );
        } catch (\DomainException) {
            // the exact-instance rethrow is proved above
        }

        $this->assertSame(1, $invocations);
        $this->assertSame(1, $this->countIn($connection->journal, 'beginTransaction'));
        $this->assertSame(1, $this->countIn($connection->journal, 'rollBack'));
        $this->assertSame(0, $this->countIn($connection->journal, 'commit'));
        $this->assertSame(
            1,
            \count(array_filter(
                $connection->scalarCalls,
                static fn (array $call): bool => str_contains($call['query'], 'set_config'),
            )),
        );
    }

    // ---------------------------------------------------------- source guards

    /**
     * The production-source guard for the canonical setting.
     *
     * **This is a review control, not a proof.** It reads tracked PHP source and
     * reports the canonical setting literal or a Firm-setting `SET`/`SET LOCAL`/
     * `SET SESSION`/`RESET`/`set_config` statement outside the approved adapter.
     * It **cannot** detect SQL assembled dynamically at runtime — from
     * concatenation, a variable, configuration, or user input — and PF-073 makes
     * no claim that it can. Review, the single-connection callback contract, and
     * least-privilege code ownership carry that prohibition alongside it.
     */
    public function test_no_production_source_outside_the_adapter_writes_the_firm_setting(): void
    {
        $offenders = [];

        foreach ($this->productionSourceFiles() as $relativePath => $source) {
            if ($relativePath === 'Infrastructure/Database/FirmTransactionManager.php') {
                continue;
            }

            if ($this->firmSettingStatements($source) !== []) {
                $offenders[] = $relativePath;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only FirmTransactionManager may name or write the canonical Firm setting.',
        );
    }

    public function test_the_adapter_itself_is_the_one_holder_of_the_setting(): void
    {
        $source = $this->adapterSource();

        $this->assertNotSame([], $this->firmSettingStatements($source));
        $this->assertStringContainsString("public const FIRM_SETTING = 'onelegalpro.firm_id';", $source);
        $this->assertSame(1, substr_count($source, "'onelegalpro.firm_id'"));
    }

    public function test_the_source_guard_detector_reports_prohibited_statements(): void
    {
        $prohibited = [
            "<?php \$connection->statement(\"set local onelegalpro.firm_id = '1'\");",
            '<?php $connection->scalar(\'select set_config(?, ?, false)\', $bindings);',
            "<?php \$pdo->exec('SET SESSION onelegalpro.firm_id = 1');",
            "<?php \$pdo->exec('RESET onelegalpro.firm_id');",
            "<?php \$name = 'onelegalpro.firm_id';",
        ];

        foreach ($prohibited as $snippet) {
            $this->assertNotSame(
                [],
                $this->firmSettingStatements($snippet),
                $snippet.' must be reported by the guard.',
            );
        }
    }

    public function test_the_source_guard_detector_ignores_unrelated_source_and_comments(): void
    {
        $allowed = [
            '<?php $firmId = $context->firmId()->toString();',
            '<?php // the canonical setting onelegalpro.firm_id is documented here',
            '<?php /** Mentions set_config and SET LOCAL only in a docblock. */',
            "<?php \$connection->scalar('select count(*) from pg_roles where rolname = current_user');",
            '<?php $settings = $this->configuration();',
        ];

        foreach ($allowed as $snippet) {
            $this->assertSame(
                [],
                $this->firmSettingStatements($snippet),
                $snippet.' must not be reported by the guard.',
            );
        }
    }

    public function test_the_adapter_imports_and_invokes_no_external_client(): void
    {
        $source = $this->withoutComments($this->adapterSource());

        preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

        $this->assertSame([
            'App\\Foundation\\Tenancy\\FirmContext',
            'Illuminate\\Database\\Connection',
        ], $matches[1]);
        $this->assertStringContainsString('Connection $connection', $source);

        foreach ([
            'Http', 'Guzzle', 'Client', 'Redis', 'Queue', 'Mail', 'Event', 'Logger', 'Cache', 'Storage',
            'curl_', 'fsockopen', 'file_get_contents', 'sleep', 'usleep', 'retry(', 'attempts',
        ] as $prohibited) {
            $this->assertStringNotContainsString(
                $prohibited,
                $source,
                'The adapter must not reference '.$prohibited.'.',
            );
        }
    }

    public function test_this_is_a_pure_phpunit_test(): void
    {
        $reflection = new \ReflectionClass(self::class);

        $this->assertSame(TestCase::class, $reflection->getParentClass()?->getName());
        $this->assertFalse($reflection->isSubclassOf(\Illuminate\Foundation\Testing\TestCase::class));

        $source = file_get_contents(__FILE__);

        $this->assertIsString($source);

        // Comments are stripped, so this file's own prose may name the traits it
        // is proving absent; only executable code counts. The needles are
        // assembled from fragments so this assertion cannot satisfy itself.
        $code = $this->withoutComments($source);

        $prohibited = [
            'Refresh'.'Database',
            'Database'.'Transactions',
            'Database'.'Migrations',
            'Tests\\'.'TestCase',
        ];

        foreach ($prohibited as $trait) {
            $this->assertSame(
                0,
                substr_count($code, $trait),
                'This suite must not use '.$trait.'.',
            );
        }
    }

    // ----------------------------------------------------------------- helpers

    private function context(string $firmId = self::FIRM_ID): FirmContext
    {
        return new FirmContext(
            firmId: TestFirmIdentifier::fromString($firmId),
            actorId: TestActorIdentifier::fromString(self::ACTOR_ID),
            correlationId: UuidV7::fromString(self::CORRELATION_ID),
        );
    }

    /** A double scripted for one completely successful run. */
    private function readyConnection(): RecordingConnection
    {
        return (new RecordingConnection)->queueScalar(
            'unprivileged',
            'unprivileged',
            null,
            self::FIRM_ID,
            self::FIRM_ID,
            null,
        );
    }

    private function notingCallback(): \Closure
    {
        return static function (Connection $managed): string {
            $managed->note('callback');

            return 'ok';
        };
    }

    private function throwingCallback(\Throwable $failure): \Closure
    {
        return static function (Connection $managed) use ($failure): never {
            $managed->note('callback');

            throw $failure;
        };
    }

    /** A callback that must never run; invoking it fails the test outright. */
    private function failingCallback(): \Closure
    {
        return function (Connection $managed): never {
            $managed->note('callback');

            $this->fail('The callback must not be invoked.');
        };
    }

    private function assertRejectedBeforeCallback(
        RecordingConnection $connection,
        string $exception,
        string $messageFragment,
    ): void {
        $this->expectExceptionOnRun($connection, $this->context(), $exception, $messageFragment);

        $this->assertNotContains('callback', $connection->journal);
    }

    private function expectExceptionOnRun(
        RecordingConnection $connection,
        FirmContext $context,
        string $exception,
        string $messageFragment,
    ): void {
        try {
            (new FirmTransactionManager($connection))->run($context, $this->failingCallback());
            $this->fail('Expected '.$exception.' was not thrown.');
        } catch (\Throwable $caught) {
            $this->assertInstanceOf($exception, $caught);
            $this->assertStringContainsString($messageFragment, $caught->getMessage());
        }
    }

    /** The message a freshly scripted identical run raises, for wording assertions. */
    private function messageOf(RecordingConnection $template, FirmContext $context): string
    {
        $connection = (new RecordingConnection)->queueScalar(...$template->scriptedOutcomes());

        try {
            (new FirmTransactionManager($connection))->run($context, $this->notingCallback());
        } catch (\Throwable $caught) {
            return $caught->getMessage();
        }

        $this->fail('The scripted run was expected to fail.');
    }

    /**
     * @param  list<string>  $journal
     */
    private function countIn(array $journal, string $entry): int
    {
        return \count(array_filter($journal, static fn (string $recorded): bool => $recorded === $entry));
    }

    private function sourcePath(): string
    {
        $path = realpath(\dirname(__DIR__, 4).self::SOURCE_PATH);

        $this->assertIsString($path);

        return $path;
    }

    private function adapterSource(): string
    {
        $source = file_get_contents($this->sourcePath());

        $this->assertIsString($source);

        return $source;
    }

    /**
     * Every PHP file under `app/`, keyed by its path relative to that directory.
     *
     * @return array<string, string>
     */
    private function productionSourceFiles(): array
    {
        $root = \dirname(__DIR__, 4).'/app';

        $this->assertDirectoryExists($root);

        $files = [];

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        ) as $file) {
            \assert($file instanceof \SplFileInfo);

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            $this->assertIsString($contents, 'Unable to read '.$file->getPathname().'.');

            $files[ltrim(str_replace($root, '', $file->getPathname()), '/')] = $contents;
        }

        $this->assertNotEmpty($files);
        ksort($files);

        return $files;
    }

    /**
     * Firm-setting statements found in PHP source, ignoring comments and
     * docblocks so prose can never trip the guard and never satisfy it either.
     *
     * @return list<string>
     */
    private function firmSettingStatements(string $source): array
    {
        $code = $this->withoutComments($source);

        $patterns = [
            '/onelegalpro\.firm_id/i',
            '/\bset_config\s*\(/i',
            '/\b(?:set\s+(?:local\s+|session\s+)?|reset\s+)[\'"]?[A-Za-z0-9_]*\.?firm_id\b/i',
        ];

        $found = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $code, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $found[] = $match;
                }
            }
        }

        return $found;
    }

    private function withoutComments(string $source): string
    {
        $stripped = '';

        foreach (token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $stripped .= \is_array($token) ? $token[1] : $token;
        }

        return $stripped;
    }
}

/**
 * A controlled `Connection` double.
 *
 * It records an ordered journal of everything the manager did, keeps the exact
 * query, bindings, and `$useReadPdo` argument of every scalar call, and returns
 * scripted outcomes — a value to return, or a `\Throwable` to raise. It opens no
 * socket and holds no PDO: the parent constructor is deliberately not called.
 */
final class RecordingConnection extends Connection
{
    /** @var list<string> */
    public array $journal = [];

    /** @var list<array{query: string, bindings: array<int, mixed>, useReadPdo: mixed}> */
    public array $scalarCalls = [];

    public int $unexpectedScalarCalls = 0;

    public int $disconnects = 0;

    /** @var list<mixed> */
    private array $scalarOutcomes = [];

    /** @var list<mixed> */
    private array $script = [];

    private ?\Throwable $commitFailure = null;

    private ?\Throwable $beginFailure = null;

    private ?\Throwable $rollBackFailure = null;

    private object $rawPdo;

    private bool $changePdoWhenBeginning = false;

    public function __construct(private int $level = 0)
    {
        $this->rawPdo = new \stdClass;
    }

    public function queueScalar(mixed ...$outcomes): self
    {
        foreach ($outcomes as $outcome) {
            $this->scalarOutcomes[] = $outcome;
            $this->script[] = $outcome;
        }

        return $this;
    }

    /** @return list<mixed> */
    public function scriptedOutcomes(): array
    {
        return $this->script;
    }

    public function failCommitWith(\Throwable $failure): self
    {
        $this->commitFailure = $failure;

        return $this;
    }

    public function failBeginWith(\Throwable $failure): self
    {
        $this->beginFailure = $failure;

        return $this;
    }

    public function changePdoOnBegin(): self
    {
        $this->changePdoWhenBeginning = true;

        return $this;
    }

    public function failRollBackWith(\Throwable $failure): self
    {
        $this->rollBackFailure = $failure;

        return $this;
    }

    public function note(string $entry): void
    {
        $this->journal[] = $entry;
    }

    public function scalar($query, $bindings = [], $useReadPdo = true)
    {
        $this->journal[] = 'scalar';
        $this->scalarCalls[] = ['query' => $query, 'bindings' => $bindings, 'useReadPdo' => $useReadPdo];

        if ($this->scalarOutcomes === []) {
            $this->unexpectedScalarCalls++;

            return null;
        }

        $outcome = array_shift($this->scalarOutcomes);

        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        return $outcome;
    }

    public function transactionLevel()
    {
        $this->journal[] = 'transactionLevel';

        return $this->level;
    }

    public function beginTransaction()
    {
        $this->journal[] = 'beginTransaction';

        if ($this->beginFailure !== null) {
            throw $this->beginFailure;
        }

        if ($this->changePdoWhenBeginning) {
            $this->rawPdo = new \stdClass;
        }

        $this->level++;
    }

    public function getRawPdo()
    {
        return $this->rawPdo;
    }

    public function commit()
    {
        $this->journal[] = 'commit';

        if ($this->commitFailure !== null) {
            throw $this->commitFailure;
        }

        $this->level--;
    }

    public function rollBack($toLevel = null)
    {
        $this->journal[] = 'rollBack';

        if ($this->rollBackFailure !== null) {
            throw $this->rollBackFailure;
        }

        $this->level = max(0, $this->level - 1);
    }

    public function disconnect()
    {
        $this->journal[] = 'disconnect';
        $this->disconnects++;
        $this->level = 0;
    }
}

final readonly class TestFirmIdentifier extends BusinessIdentifier {}

final readonly class TestActorIdentifier extends BusinessIdentifier {}
