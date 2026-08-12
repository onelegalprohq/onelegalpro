<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Foundation\Tenancy\FirmContext;
use Illuminate\Database\Connection;

/**
 * PF-073 — the single application transaction boundary.
 *
 * One call equals one outer PostgreSQL transaction: the connection's role and
 * session state are verified first, the canonical Firm setting is established
 * transaction-locally and confirmed, the callback runs against the exact managed
 * connection, and the transaction commits or rolls back as a unit.
 *
 * This adapter lives outside `app/Foundation` deliberately. It depends on
 * Laravel's concrete `Connection` — it must hand the exact managed instance to
 * the callback and must be able to quarantine a contaminated checkout through
 * `disconnect()` — while the framework-independent `FirmContext` stays under
 * `App\Foundation\Tenancy`.
 *
 * **It is not authentication, membership verification, authorization,
 * repository scoping, Row-Level Security, or an Ethical Wall**, and it does not
 * by itself complete tenant isolation. Possessing a `FirmContext` proves no
 * membership, entitlement, role, permission, Ethical Wall outcome, or resource
 * access; this class neither reconstructs, mutates, caches, serializes, nor logs
 * the context it is given.
 *
 * Three deliberate absences, each an approved decision rather than an omission:
 *
 * - **No nesting, joining, savepoints, or context switching.** Every ambient
 *   transaction is rejected before any statement is issued, whether it would use
 *   the same Firm or a different one, so setting establishment happens exactly
 *   once and no inner rollback can leave a still-live outer transaction with
 *   ambiguous ownership.
 * - **No automatic retry.** `\RuntimeException` is deliberately *not* a
 *   machine-readable retry discriminator; a future retry facility must introduce
 *   separately approved typed outcome classification rather than parsing these
 *   messages or treating every `\RuntimeException` alike.
 * - **No external call, event, outbox write, audit write, or logging.** The
 *   caller's contract forbids external HTTP, provider, messaging, object-storage,
 *   AI, queue-push, sleep, and polling work inside the callback; this adapter
 *   cannot inspect callback contents and makes no runtime guarantee about them.
 */
final class FirmTransactionManager
{
    /**
     * The one platform-wide PostgreSQL setting carrying the current Firm.
     *
     * The dot is required for a PostgreSQL custom setting; the qualified prefix
     * avoids collision with PostgreSQL and third-party settings. No module
     * repeats, replaces, or writes this setting — a production-source guard in
     * the focused test suite enforces that.
     */
    public const FIRM_SETTING = 'onelegalpro.firm_id';

    /**
     * Role-probe verdicts.
     *
     * These are bound parameters, not interpolated SQL: each probe returns the
     * verdict token the database itself selected. Anything other than
     * `unprivileged` — including `null`, which is what an absent catalogue row
     * yields — is treated as privileged or indeterminate and fails closed.
     */
    private const ROLE_PRIVILEGED = 'privileged';

    private const ROLE_UNPRIVILEGED = 'unprivileged';

    private const SUPERUSER_PROBE = 'select case when rolsuper then ? else ? end from pg_roles where rolname = current_user';

    private const BYPASSRLS_PROBE = 'select case when rolbypassrls then ? else ? end from pg_roles where rolname = current_user';

    /**
     * Reads the canonical setting, mapping both "absent" and "present but empty"
     * onto `null` — the two states that are equally unusable as Firm context.
     */
    private const SETTING_PROBE = "select nullif(current_setting(?, true), '')";

    /**
     * `set_config(..., true)` is the transaction-local form, equivalent to
     * `SET LOCAL`. Session-level `SET` and `set_config(..., false)` are
     * prohibited without exception (ADR-016 Decision 8).
     */
    private const ESTABLISH_SETTING = 'select set_config(?, ?, true)';

    private const MESSAGE_AMBIENT_TRANSACTION = 'A Firm transaction must begin at transaction level zero. An ambient transaction is already open, and PF-073 supports no nesting, joining, savepoint, or context switch.';

    private const MESSAGE_SUPERUSER = 'The database role serving this connection is a superuser, or its privilege state is indeterminate. The connection was discarded and no Firm transaction was opened.';

    private const MESSAGE_BYPASSRLS = 'The database role serving this connection holds BYPASSRLS, or its privilege state is indeterminate. The connection was discarded and no Firm transaction was opened.';

    private const MESSAGE_CONTAMINATED = 'The connection already carried a Firm setting before the transaction began. The connection was discarded and no Firm transaction was opened.';

    private const MESSAGE_SETTING_NOT_ESTABLISHED = 'The transaction-local Firm setting could not be established. The transaction did not commit, and the connection was discarded.';

    private const MESSAGE_BEGIN_FAILED = 'The Firm transaction could not begin. The transaction did not commit, and the connection was discarded.';

    private const MESSAGE_CONNECTION_CHANGED = 'The database connection changed while the Firm transaction was beginning, so its role and session state were not the state verified by preflight. The transaction did not commit, and the connection was discarded.';

    private const MESSAGE_SETTING_CHANGED = 'The transaction-local Firm setting no longer matched the supplied Firm after callback execution. The transaction did not commit, and the connection was discarded.';

    private const MESSAGE_SETTING_UNVERIFIED = 'The transaction-local Firm setting could not be confirmed unchanged after callback execution. The transaction did not commit, and the connection was discarded.';

    private const MESSAGE_ROLLBACK_FAILED = 'Rollback failed after a Firm transaction failure. The transaction did not commit, the connection state is unknown, and the connection was discarded. The original failure is chained as evidence.';

    private const MESSAGE_CLEANUP_AFTER_ROLLBACK = 'Connection cleanup could not be verified after rollback. The transaction did not commit, the connection state is unknown, and the connection was discarded. The original failure is chained as evidence.';

    private const MESSAGE_COMMIT_FAILED = 'The Firm transaction failed to commit. The transaction did not commit, and the connection was discarded.';

    private const MESSAGE_CLEANUP_AFTER_COMMIT = 'The transaction committed durably, but connection cleanup could not be verified. The connection was discarded. Do not retry this operation as though the commit had failed.';

    private const MESSAGE_SETTING_SURVIVED = 'The canonical Firm setting was still readable on the connection after the transaction ended.';

    /**
     * The concrete Laravel connection is deliberate and is not hidden behind a
     * narrower contract: PF-073 must hand this exact instance to the callback and
     * must be able to quarantine a contaminated checkout through `disconnect()`.
     *
     * The imported Connection name is the approved Pint-compatible spelling of
     * the same concrete Laravel type used by the PHPStan callback contract.
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Run one callback inside one Firm-scoped PostgreSQL transaction.
     *
     * The callback receives the exact managed connection and must perform all of
     * its database work through it: a facade, a differently named connection, or
     * an independently resolved connection does not carry the transaction-local
     * setting. PHP cannot make that unrepresentable, so it stays a caller
     * contract covered by each consuming story's own integration tests.
     *
     * Reads receive the same boundary as writes — autocommit reads of
     * Firm-scoped relations are prohibited (ADR-016 Decision 8).
     *
     * @template TResult
     *
     * @param  callable(Connection): TResult  $callback
     * @return TResult
     *
     * @throws \LogicException when an ambient transaction is already open
     * @throws \RuntimeException when the connection is privileged, contaminated,
     *                           or its state after the transaction cannot be verified
     * @throws \Throwable the exact instance the callback threw, when rollback and
     *                    cleanup verification both succeed
     */
    public function run(FirmContext $context, callable $callback): mixed
    {
        if ($this->connection->transactionLevel() !== 0) {
            throw new \LogicException(self::MESSAGE_AMBIENT_TRANSACTION);
        }

        $firmId = $context->firmId()->toString();

        $this->verifyConnectionIsUsable();

        $verifiedPdo = $this->connection->getRawPdo();

        try {
            $this->connection->beginTransaction();
        } catch (\Throwable $failure) {
            $this->connection->disconnect();

            throw new \RuntimeException(self::MESSAGE_BEGIN_FAILED, 0, $failure);
        }

        if ($this->connection->getRawPdo() !== $verifiedPdo) {
            $this->abandonTransaction(self::MESSAGE_CONNECTION_CHANGED, null);
        }

        try {
            $established = $this->connection->scalar(
                self::ESTABLISH_SETTING,
                [self::FIRM_SETTING, $firmId],
                false,
            );
        } catch (\Throwable $failure) {
            $this->abandonTransaction(self::MESSAGE_SETTING_NOT_ESTABLISHED, $failure);
        }

        if ($established !== $firmId) {
            $this->abandonTransaction(self::MESSAGE_SETTING_NOT_ESTABLISHED, null);
        }

        try {
            $result = $callback($this->connection);
        } catch (\Throwable $original) {
            $this->rollBackAfterCallbackFailure($original);
        }

        try {
            $observed = $this->connection->scalar(self::SETTING_PROBE, [self::FIRM_SETTING], false);
        } catch (\Throwable $failure) {
            $this->abandonTransaction(self::MESSAGE_SETTING_UNVERIFIED, $failure);
        }

        if ($observed !== $firmId) {
            $this->abandonTransaction(self::MESSAGE_SETTING_CHANGED, null);
        }

        try {
            $this->connection->commit();
        } catch (\Throwable $failure) {
            $this->connection->disconnect();

            throw new \RuntimeException(self::MESSAGE_COMMIT_FAILED, 0, $failure);
        }

        $cleanupFailure = $this->cleanupFailure();

        if ($cleanupFailure !== null) {
            $this->connection->disconnect();

            throw new \RuntimeException(self::MESSAGE_CLEANUP_AFTER_COMMIT, 0, $cleanupFailure);
        }

        return $result;
    }

    /**
     * Verify, on the write PDO, that this checkout may carry Firm context at all.
     *
     * A connection is never trusted merely because the application created it: a
     * pooler, a failover, or a reset can change the role or leave session state
     * behind. A privileged role, an indeterminate answer, or a leftover Firm
     * setting all fail closed here, before the transaction opens and before any
     * protected callback work.
     *
     * The contaminated connection is **discarded, never normalized**: no
     * `RESET`, no session-level `SET`, and no `set_config(..., false)` is
     * attempted anywhere in this class.
     */
    private function verifyConnectionIsUsable(): void
    {
        $this->verifyRoleAttribute(self::SUPERUSER_PROBE, self::MESSAGE_SUPERUSER);
        $this->verifyRoleAttribute(self::BYPASSRLS_PROBE, self::MESSAGE_BYPASSRLS);

        try {
            $leaked = $this->connection->scalar(self::SETTING_PROBE, [self::FIRM_SETTING], false);
        } catch (\Throwable $failure) {
            $this->quarantine(self::MESSAGE_CONTAMINATED, $failure);
        }

        if ($leaked !== null) {
            $this->quarantine(self::MESSAGE_CONTAMINATED, null);
        }
    }

    private function verifyRoleAttribute(string $probe, string $message): void
    {
        try {
            $verdict = $this->connection->scalar(
                $probe,
                [self::ROLE_PRIVILEGED, self::ROLE_UNPRIVILEGED],
                false,
            );
        } catch (\Throwable $failure) {
            $this->quarantine($message, $failure);
        }

        if ($verdict !== self::ROLE_UNPRIVILEGED) {
            $this->quarantine($message, null);
        }
    }

    /**
     * The callback failed: roll back, verify the connection is clean, and give
     * the caller back the exact throwable it raised.
     *
     * When rollback or cleanup verification fails instead, the failure is not
     * hidden in order to preserve the callback throwable — connection state is
     * then unknown, so a `\RuntimeException` is raised with **the original
     * callback failure chained as evidence**, as the approved contract requires.
     * The proximate infrastructure failure is deliberately not the chained
     * exception, because PHP carries one previous and the contract names the
     * original.
     */
    private function rollBackAfterCallbackFailure(\Throwable $original): never
    {
        try {
            $this->connection->rollBack();
        } catch (\Throwable) {
            $this->connection->disconnect();

            throw new \RuntimeException(self::MESSAGE_ROLLBACK_FAILED, 0, $original);
        }

        if ($this->cleanupFailure() !== null) {
            $this->connection->disconnect();

            throw new \RuntimeException(self::MESSAGE_CLEANUP_AFTER_ROLLBACK, 0, $original);
        }

        throw $original;
    }

    /**
     * Abandon a transaction whose Firm setting could not be established or could
     * not be confirmed unchanged.
     *
     * The connection is discarded either way, so a rollback that itself fails
     * changes nothing about the outcome and never displaces the reported cause.
     */
    private function abandonTransaction(string $message, ?\Throwable $previous): never
    {
        try {
            $this->connection->rollBack();
        } catch (\Throwable) {
            $this->connection->disconnect();

            throw new \RuntimeException(
                self::MESSAGE_ROLLBACK_FAILED,
                0,
                $previous ?? new \RuntimeException($message),
            );
        }

        $this->quarantine($message, $previous);
    }

    private function quarantine(string $message, ?\Throwable $previous): never
    {
        $this->connection->disconnect();

        throw new \RuntimeException($message, 0, $previous);
    }

    /**
     * Why cleanup could not be verified after the transaction ended, or `null`
     * when the setting is absent or unusably empty on the same write connection.
     *
     * A probe that throws and a setting that survived are the same outcome —
     * unverified cleanup — and both are returned as evidence to chain.
     */
    private function cleanupFailure(): ?\Throwable
    {
        try {
            $remaining = $this->connection->scalar(self::SETTING_PROBE, [self::FIRM_SETTING], false);
        } catch (\Throwable $failure) {
            return $failure;
        }

        return $remaining === null ? null : new \RuntimeException(self::MESSAGE_SETTING_SURVIVED);
    }
}
