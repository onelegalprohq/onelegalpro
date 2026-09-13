<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tenancy;

use App\Application\Tenancy\ActiveFirmMembershipAssertion;
use App\Application\Tenancy\AuthenticatedActor;
use App\Application\Tenancy\FirmMembershipVerifier;
use App\Application\Tenancy\FirmResolutionDenied;
use App\Application\Tenancy\VerifiedFirmContextResolver;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Identity\UuidV7;
use App\Foundation\Domain\Identity\UuidV7Generator;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class VerifiedFirmContextResolverTest extends TestCase
{
    public function test_it_constructs_a_context_only_from_an_agreeing_assertion(): void
    {
        $firmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000001');
        $actorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000002');
        $actor = new TestAuthenticatedActor($actorId);
        $assertion = new TestMembershipAssertion($firmId, $actorId);
        $verifier = new TestVerifier($assertion);
        $correlationId = UuidV7::fromString('018f0000-0000-7000-8000-000000000003');
        $generator = new TestUuidV7Generator($correlationId);

        $context = (new VerifiedFirmContextResolver($verifier, $generator))->resolve($actor, $firmId);

        self::assertSame(1, $verifier->calls);
        self::assertSame($firmId, $verifier->receivedFirmId);
        self::assertSame($actor, $verifier->receivedActor);
        self::assertSame($firmId, $context->firmId());
        self::assertSame($actorId, $context->actorId());
        self::assertSame($correlationId, $context->correlationId());
        self::assertSame(1, $generator->calls);
    }

    public function test_it_denies_an_assertion_with_a_different_firm_without_generating_a_correlation_id(): void
    {
        $firmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000011');
        $otherFirmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000012');
        $actorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000013');
        $generator = new TestUuidV7Generator(UuidV7::fromString('018f0000-0000-7000-8000-000000000014'));
        $resolver = new VerifiedFirmContextResolver(
            new TestVerifier(new TestMembershipAssertion($otherFirmId, $actorId)),
            $generator,
        );

        $exception = $this->expectDenied(fn (): mixed => $resolver->resolve(new TestAuthenticatedActor($actorId), $firmId));

        self::assertSame('Firm context could not be resolved.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame(0, $generator->calls);
    }

    public function test_it_uses_one_actor_identifier_instance_for_the_entire_resolution(): void
    {
        $firmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000015');
        $actorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000016');
        $differentActorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000017');
        $actor = new TestChangingActor($actorId, $differentActorId);
        $resolver = new VerifiedFirmContextResolver(
            new TestVerifier(new TestMembershipAssertion($firmId, $actorId)),
            new TestUuidV7Generator(UuidV7::fromString('018f0000-0000-7000-8000-000000000018')),
        );

        $context = $resolver->resolve($actor, $firmId);

        self::assertSame(1, $actor->calls);
        self::assertSame($actorId, $context->actorId());
    }

    public function test_it_denies_an_assertion_with_a_different_actor_without_generating_a_correlation_id(): void
    {
        $firmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000019');
        $actorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000020');
        $otherActorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000021');
        $generator = new TestUuidV7Generator(UuidV7::fromString('018f0000-0000-7000-8000-000000000022'));
        $resolver = new VerifiedFirmContextResolver(
            new TestVerifier(new TestMembershipAssertion($firmId, $otherActorId)),
            $generator,
        );

        $this->expectDenied(fn (): mixed => $resolver->resolve(new TestAuthenticatedActor($actorId), $firmId));

        self::assertSame(0, $generator->calls);
    }

    public function test_it_normalizes_any_verifier_failure_to_the_single_denial(): void
    {
        $firmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000021');
        $actorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000022');
        $generator = new TestUuidV7Generator(UuidV7::fromString('018f0000-0000-7000-8000-000000000023'));
        $resolver = new VerifiedFirmContextResolver(
            new TestThrowingVerifier,
            $generator,
        );

        $exception = $this->expectDenied(fn (): mixed => $resolver->resolve(new TestAuthenticatedActor($actorId), $firmId));

        self::assertSame('Firm context could not be resolved.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
        self::assertSame(0, $generator->calls);
    }

    public function test_it_does_not_translate_a_correlation_generation_failure(): void
    {
        $firmId = TestFirmIdentifier::fromString('018f0000-0000-7000-8000-000000000031');
        $actorId = TestActorIdentifier::fromString('018f0000-0000-7000-8000-000000000032');
        $this->expectException(RandomException::class);

        (new VerifiedFirmContextResolver(
            new TestVerifier(new TestMembershipAssertion($firmId, $actorId)),
            new TestFailingUuidV7Generator,
        ))->resolve(new TestAuthenticatedActor($actorId), $firmId);
    }

    /** @param callable(): mixed $operation */
    private function expectDenied(callable $operation): FirmResolutionDenied
    {
        try {
            $operation();
        } catch (FirmResolutionDenied $exception) {
            return $exception;
        }

        self::fail('Expected FirmResolutionDenied.');
    }
}

final readonly class TestFirmIdentifier extends BusinessIdentifier {}

final readonly class TestActorIdentifier extends BusinessIdentifier {}

final readonly class TestAuthenticatedActor implements AuthenticatedActor
{
    public function __construct(private BusinessIdentifier $actorId) {}

    public function actorId(): BusinessIdentifier
    {
        return $this->actorId;
    }
}

final class TestChangingActor implements AuthenticatedActor
{
    public int $calls = 0;

    public function __construct(private BusinessIdentifier $first, private BusinessIdentifier $second) {}

    public function actorId(): BusinessIdentifier
    {
        $this->calls++;

        return $this->calls === 1 ? $this->first : $this->second;
    }
}

final readonly class TestMembershipAssertion implements ActiveFirmMembershipAssertion
{
    public function __construct(private BusinessIdentifier $firmId, private BusinessIdentifier $actorId) {}

    public function firmId(): BusinessIdentifier
    {
        return $this->firmId;
    }

    public function actorId(): BusinessIdentifier
    {
        return $this->actorId;
    }
}

final class TestVerifier implements FirmMembershipVerifier
{
    public int $calls = 0;

    public ?AuthenticatedActor $receivedActor = null;

    public ?BusinessIdentifier $receivedFirmId = null;

    public function __construct(private ActiveFirmMembershipAssertion $assertion) {}

    public function verifyActiveMembership(AuthenticatedActor $actor, BusinessIdentifier $firmId): ActiveFirmMembershipAssertion
    {
        $this->calls++;
        $this->receivedActor = $actor;
        $this->receivedFirmId = $firmId;

        return $this->assertion;
    }
}

final class TestThrowingVerifier implements FirmMembershipVerifier
{
    public function verifyActiveMembership(AuthenticatedActor $actor, BusinessIdentifier $firmId): ActiveFirmMembershipAssertion
    {
        throw new \RuntimeException('Internal verifier detail.');
    }
}

final class TestUuidV7Generator implements UuidV7Generator
{
    public int $calls = 0;

    public function __construct(private UuidV7 $value) {}

    public function generate(): UuidV7
    {
        $this->calls++;

        return $this->value;
    }
}

final class TestFailingUuidV7Generator implements UuidV7Generator
{
    public function generate(): UuidV7
    {
        throw new RandomException('Entropy unavailable.');
    }
}
