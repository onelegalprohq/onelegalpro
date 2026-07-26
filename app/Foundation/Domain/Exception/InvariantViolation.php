<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Exception;

/**
 * A domain invariant was violated (PF-049).
 *
 * Thrown when an object would otherwise reach a state its own rules forbid —
 * a guarantee the domain must always hold has been broken. Adds no behavior
 * beyond {@see DomainException}.
 *
 * Messages are developer-facing diagnostics, never external-caller output.
 */
final class InvariantViolation extends DomainException {}
