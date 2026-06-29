<?php

declare(strict_types=1);

namespace AzureOss\Storage\Blob\Exceptions;

/**
 * Indicates that a SAS cannot be signed because required signing state is missing.
 */
final class UnableToGenerateSasException extends \Exception {}
