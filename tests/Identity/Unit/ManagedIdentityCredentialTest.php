<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\ManagedIdentityCredential;
use AzureOss\Identity\TokenRequestContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ManagedIdentityCredentialTest extends TestCase
{
    #[Test]
    public function managed_identity_credential_requires_single_scope(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ManagedIdentityCredential)->getToken(new TokenRequestContext([
            'https://graph.microsoft.com/.default',
            'https://vault.azure.net/.default',
        ]));
    }
}
