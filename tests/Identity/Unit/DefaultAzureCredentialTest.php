<?php

declare(strict_types=1);

namespace AzureOss\Tests\Identity\Unit;

use AzureOss\Identity\ChainedTokenCredential;
use AzureOss\Identity\DefaultAzureCredential;
use AzureOss\Identity\DefaultAzureCredentialOptions;
use AzureOss\Identity\EnvironmentCredential;
use AzureOss\Identity\ManagedIdentityCredential;
use AzureOss\Identity\TokenCredential;
use AzureOss\Identity\WorkloadIdentityCredential;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefaultAzureCredentialTest extends TestCase
{
    /**
     * @return list<TokenCredential>
     */
    private function chainSources(DefaultAzureCredential $credential): array
    {
        $chainGetter = \Closure::bind(
            static fn (DefaultAzureCredential $credential): TokenCredential => $credential->chain,
            null,
            DefaultAzureCredential::class,
        );

        $sourcesGetter = \Closure::bind(
            static fn (ChainedTokenCredential $chain): array => $chain->sources,
            null,
            ChainedTokenCredential::class,
        );

        $chain = $chainGetter($credential);
        self::assertInstanceOf(ChainedTokenCredential::class, $chain);

        /** @var list<TokenCredential> $sources */
        $sources = $sourcesGetter($chain);

        return $sources;
    }

    #[Test]
    public function excludes_managed_identity_credential_by_default(): void
    {
        $sources = $this->chainSources(new DefaultAzureCredential);

        self::assertCount(2, $sources);
        self::assertInstanceOf(EnvironmentCredential::class, $sources[0]);
        self::assertInstanceOf(WorkloadIdentityCredential::class, $sources[1]);
    }

    #[Test]
    public function can_include_managed_identity_credential(): void
    {
        $sources = $this->chainSources(new DefaultAzureCredential(
            new DefaultAzureCredentialOptions(excludeManagedIdentityCredential: false),
        ));

        self::assertCount(3, $sources);
        self::assertInstanceOf(ManagedIdentityCredential::class, $sources[2]);
    }

    #[Test]
    public function can_exclude_environment_and_workload_credentials(): void
    {
        $sources = $this->chainSources(new DefaultAzureCredential(
            new DefaultAzureCredentialOptions(
                excludeEnvironmentCredential: true,
                excludeWorkloadIdentityCredential: true,
                excludeManagedIdentityCredential: false,
            ),
        ));

        self::assertCount(1, $sources);
        self::assertInstanceOf(ManagedIdentityCredential::class, $sources[0]);
    }

    #[Test]
    public function can_exclude_all_credentials(): void
    {
        $sources = $this->chainSources(new DefaultAzureCredential(
            new DefaultAzureCredentialOptions(
                excludeEnvironmentCredential: true,
                excludeWorkloadIdentityCredential: true,
                excludeManagedIdentityCredential: true,
            ),
        ));

        self::assertSame([], $sources);
    }
}
