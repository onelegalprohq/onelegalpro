<?php

declare(strict_types=1);

namespace App\Foundation\Domain\Exception;

/**
 * Base class for every OneLegalPro domain failure (PF-049).
 *
 * This is `App\Foundation\Domain\Exception\DomainException`, deliberately
 * distinct from PHP's global `\DomainException`, which it does not extend.
 * It extends `\RuntimeException` and implements {@see FoundationException}, so
 * a caller may catch a domain failure through either the framework-independent
 * marker contract or the standard PHP exception hierarchy.
 *
 * Future module-domain exceptions extend this class rather than defining a
 * competing taxonomy. It adds no constructor: the inherited standard exception
 * constructor (message, code, previous) is sufficient.
 *
 * Messages are developer-facing diagnostics, never external-caller output, and
 * never carry tenant identifiers, actor identities, credentials, session data,
 * client or matter content, or privileged narrative.
 */
abstract class DomainException extends \RuntimeException implements FoundationException {}
