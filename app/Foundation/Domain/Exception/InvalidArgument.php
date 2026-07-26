<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Exception;

/**
 * An argument supplied to a domain operation is not acceptable (PF-049).
 *
 * Thrown when a caller supplies a value the domain cannot accept — malformed,
 * out of range, or otherwise outside the operation's contract. Adds no behavior
 * beyond {@see DomainException}.
 *
 * Messages are developer-facing diagnostics, never external-caller output.
 */
final class InvalidArgument extends DomainException {}
