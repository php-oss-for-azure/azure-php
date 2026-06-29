<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share\Exceptions;

/**
 * Indicates that a URI does not identify the required share, directory, or file resource.
 */
final class InvalidShareUriException extends \Exception {}
