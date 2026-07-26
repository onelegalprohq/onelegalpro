<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Exception;

/**
 * The single marker contract for every OneLegalPro domain failure (PF-049).
 *
 * A caller catches this interface to handle any Foundation or module-domain
 * failure without coupling to Laravel, HTTP, or persistence. It declares no
 * members of its own: the inherited \Throwable contract is sufficient.
 *
 * Exception messages carrying this contract are developer-facing diagnostics.
 * They must never be returned verbatim to an external caller, and they must
 * never carry tenant identifiers, actor identities, credentials, session data,
 * client or matter content, or privileged narrative.
 */
interface FoundationException extends \Throwable {}
