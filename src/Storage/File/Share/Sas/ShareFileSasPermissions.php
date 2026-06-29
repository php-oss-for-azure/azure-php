<?php

declare(strict_types=1);

namespace AzureOss\Storage\File\Share\Sas;

/**
 * Selects the operations granted by a file-scoped file share SAS.
 */
final class ShareFileSasPermissions
{
    public function __construct(
        public bool $read = false,
        public bool $create = false,
        public bool $write = false,
        public bool $delete = false,
    ) {}

    public function __toString(): string
    {
        $permissions = '';

        if ($this->read) {
            $permissions .= 'r';
        }
        if ($this->create) {
            $permissions .= 'c';
        }
        if ($this->write) {
            $permissions .= 'w';
        }
        if ($this->delete) {
            $permissions .= 'd';
        }

        return $permissions;
    }
}
