<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share\Exceptions;

/**
 * Indicates that a SAS cannot be signed because no shared-key credential is available.
 */
final class UnableToGenerateSasException extends \Exception {}
