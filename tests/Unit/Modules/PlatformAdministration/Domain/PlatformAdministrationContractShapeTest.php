<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain;

use App\Foundation\Domain\Event\DomainEvent;
use App\Foundation\Domain\Identity\BusinessIdentifier;
use App\Foundation\Domain\Model\AggregateRoot;
use App\Foundation\Domain\Model\Entity;
use App\Foundation\Domain\Model\ValueObject;
use App\Modules\PlatformAdministration\Domain\Aggregates\Firm;
use App\Modules\PlatformAdministration\Domain\Events\FirmCreated;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmId;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmJurisdiction;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmLifecycleState;
use App\Modules\PlatformAdministration\Domain\ValueObjects\FirmName;
use PHPUnit\Framework\TestCase;

/**
 * PA-001 — structural contract guard for `app/Modules`.
 *
 * This suite proves by reflection and by source inspection what behaviour
 * alone cannot: that the module contains **exactly** the approved files, that
 * each type has **exactly** the approved shape, and that the absences the
 * contract requires — no transition, no setter, no version, no persistence, no
 * serialization, no authorization, no framework dependency — are genuinely
 * absent rather than merely unexercised.
 *
 * **Absence is asserted against the enumerated member list, not by probing for
 * names one at a time.** A test that only checked for a method called
 * `activate()` would pass the day someone added `markActive()`. Enumerating
 * every declared member and comparing it to the approved list makes any new
 * member a failure until it is deliberately approved.
 *
 * A pure unit test: it extends PHPUnit's TestCase directly, boots no Laravel
 * application, touches no database, and reads only tracked source files.
 */
final class PlatformAdministrationContractShapeTest extends TestCase
{
    /**
     * Every PHP source file the module is allowed to contain, relative to
     * `app/Modules`, and the class each declares.
     *
     * @var array<string, class-string>
     */
    private const APPROVED_SOURCE_FILES = [
        'PlatformAdministration/Domain/Aggregates/Firm.php' => Firm::class,
        'PlatformAdministration/Domain/Events/FirmCreated.php' => FirmCreated::class,
        'PlatformAdministration/Domain/ValueObjects/FirmId.php' => FirmId::class,
        'PlatformAdministration/Domain/ValueObjects/FirmJurisdiction.php' => FirmJurisdiction::class,
        'PlatformAdministration/Domain/ValueObjects/FirmLifecycleState.php' => FirmLifecycleState::class,
        'PlatformAdministration/Domain/ValueObjects/FirmName.php' => FirmName::class,
    ];

    /**
     * Namespace prefixes no module source file may ever reference.
     *
     * A denylist rather than an allowlist, on the same reasoning the Foundation
     * guard records: it rejects the specific surfaces the Domain layer must
     * never touch, without banning every third-party namespace forever.
     *
     * @var list<string>
     */
    private const FORBIDDEN_NAMESPACE_PREFIXES = [
        'Illuminate\\',
        'Laravel\\',
        'Livewire\\',
        'Inertia\\',
        'Orchestra\\',
        'Facade\\',
        'Carbon\\',
    ];

    /**
     * Laravel global helper functions no module source file may call.
     *
     * @var list<string>
     */
    private const FORBIDDEN_GLOBAL_HELPERS = [
        'abort', 'abort_if', 'abort_unless', 'app', 'auth', 'back', 'base_path',
        'bcrypt', 'broadcast', 'cache', 'config', 'config_path', 'cookie',
        'csrf_token', 'database_path', 'dispatch', 'encrypt', 'decrypt', 'env',
        'event', 'info', 'logger', 'now', 'old', 'policy', 'public_path',
        'redirect', 'report', 'request', 'rescue', 'resolve', 'resource_path',
        'response', 'route', 'session', 'storage_path', 'today', 'to_route',
        'trans', 'url', 'validator', 'view',
    ];

    /**
     * Symbols and literals that would mean PA-001 had reached into another
     * story's territory.
     *
     * `PF-081`'s ports and `PF-080`'s context carrier are named here because
     * the contract forbids referencing either; `FirmTransactionManager` and the
     * transaction-local setting literal are `PF-073`'s and are neither imported
     * nor invoked; the rest name persistence, dispatch, and authorization
     * machinery that PA-001 delivers none of.
     *
     * @var list<string>
     */
    private const FORBIDDEN_SYMBOLS = [
        'FirmContext',
        'App\\Application\\Tenancy',
        'FirmTransactionManager',
        'onelegalpro.firm_id',
        'CandidateFirmDirectory',
        'CandidateFirmReference',
        'Eloquent',
        'Repository',
        'ServiceProvider',
        'Migration',
        'Schema',
        'Blueprint',
        'FirmProvisioning',
        'SubscriptionEntitlement',
        'EthicalWall',
        'PrivilegedAccessGrant',
        'FirmMembership',
    ];

    // ---------------------------------------------------------------- inventory

    public function test_app_modules_contains_exactly_the_approved_source_files(): void
    {
        $this->assertSame(
            array_keys(self::APPROVED_SOURCE_FILES),
            array_keys($this->moduleSourceFiles()),
            'app/Modules must contain exactly the six approved PA-001 source files.',
        );
    }

    public function test_the_module_readme_exists(): void
    {
        $this->assertFileExists(
            $this->modulesRoot().'/PlatformAdministration/README.md',
            'The module README is the standing convention record and is required.',
        );
    }

    /**
     * The directories PA-001 must not create.
     *
     * Creating any of them would pre-create another story's type: PF-060,
     * PF-061, and PF-063 are all Backlog, and the module blueprint is a
     * template rather than an instruction to create empty directories.
     */
    public function test_no_unapproved_module_directory_or_service_provider_exists(): void
    {
        $module = $this->modulesRoot().'/PlatformAdministration';

        foreach (['Application', 'Infrastructure', 'Interface', 'Database', 'Routes', 'Config', 'Tests'] as $directory) {
            $this->assertDirectoryDoesNotExist($module.'/'.$directory, $directory.' is not PA-001\'s to create.');
        }

        $this->assertFileDoesNotExist($module.'/ModuleServiceProvider.php', 'PA-001 registers nothing.');
    }

    public function test_app_modules_contains_exactly_the_platform_administration_module(): void
    {
        $entries = scandir($this->modulesRoot());

        $this->assertIsArray($entries);

        $this->assertSame(
            ['PlatformAdministration'],
            array_values(array_diff($entries, ['.', '..'])),
            'PA-001 creates exactly one module and no other business module.',
        );
    }

    // ------------------------------------------------------------ file hygiene

    public function test_every_module_source_file_declares_strict_types(): void
    {
        foreach ($this->moduleSourceFiles() as $relativePath => $source) {
            $this->assertMatchesRegularExpression(
                '/^<\?php\s+declare\(strict_types=1\);/',
                $source,
                $relativePath.' must declare strict types.',
            );
        }
    }

    public function test_every_module_namespace_agrees_with_its_path(): void
    {
        foreach (self::APPROVED_SOURCE_FILES as $relativePath => $class) {
            $expected = 'App\\Modules\\'.str_replace('/', '\\', \dirname($relativePath));

            $this->assertSame(
                $expected,
                (new \ReflectionClass($class))->getNamespaceName(),
                $relativePath.' must live in the namespace matching its path.',
            );

            $this->assertSame(
                $this->modulesRoot().'/'.$relativePath,
                (new \ReflectionClass($class))->getFileName(),
                $class.' must be declared at exactly '.$relativePath.'.',
            );
        }
    }

    // ------------------------------------------------------ dependency guards

    public function test_no_module_source_file_references_a_framework_namespace(): void
    {
        foreach ($this->moduleSourceFiles() as $relativePath => $source) {
            foreach (self::FORBIDDEN_NAMESPACE_PREFIXES as $prefix) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![A-Za-z0-9_])\\\\?'.preg_quote($prefix, '/').'/',
                    $this->withoutComments($source),
                    $relativePath.' must not depend on the '.rtrim($prefix, '\\').' namespace.',
                );
            }
        }
    }

    public function test_no_module_source_file_calls_a_laravel_global_helper(): void
    {
        foreach ($this->moduleSourceFiles() as $relativePath => $source) {
            $this->assertSame(
                [],
                $this->globalHelperCalls($source),
                $relativePath.' must not call a Laravel global helper.',
            );
        }
    }

    public function test_no_module_source_file_references_another_storys_symbol(): void
    {
        foreach ($this->moduleSourceFiles() as $relativePath => $source) {
            $code = $this->withoutComments($source);

            foreach (self::FORBIDDEN_SYMBOLS as $symbol) {
                $this->assertStringNotContainsString(
                    $symbol,
                    $code,
                    $relativePath.' must not reference '.$symbol.'.',
                );
            }
        }
    }

    /**
     * Regression cover for the helper detector itself.
     *
     * The detector is shared with the guards above, so what it reports and what
     * is enforced can never drift apart. A leading backslash makes a call
     * resolve to the *global* function, so `\config(...)` is exactly as
     * prohibited as `config(...)`; a declaration, an object or static method, a
     * namespaced function, a comment, a string literal, and a longer identifier
     * must all stay silent.
     */
    public function test_the_helper_detector_distinguishes_calls_from_mentions(): void
    {
        $prohibited = [
            "config('app.name');" => 'config',
            "\\config('app.name');" => 'config',
            'now();' => 'now',
            '\now();' => 'now',
            'CONFIG();' => 'config',
        ];

        foreach ($prohibited as $snippet => $expected) {
            $this->assertSame([$expected], $this->globalHelperCalls('<?php '.$snippet), $snippet.' must be reported.');
        }

        $allowed = [
            '$service->config();',
            'Service::config();',
            'self::now();',
            'Vendor\config();',
            "function config(): void\n{\n}",
            '// config(\'app.name\') named only in a comment',
            "\$literal = 'now()';",
            'reconfigure();',
            '$now = 1;',
        ];

        foreach ($allowed as $snippet) {
            $this->assertSame([], $this->globalHelperCalls('<?php '.$snippet), $snippet.' must not be reported.');
        }
    }

    // ------------------------------------------------------------------ FirmId

    public function test_firm_id_is_a_final_readonly_business_identifier(): void
    {
        $reflection = new \ReflectionClass(FirmId::class);

        $this->assertTrue($reflection->isFinal(), 'FirmId must be final.');
        $this->assertTrue($reflection->isReadOnly(), 'FirmId must be readonly.');
        $this->assertSame(BusinessIdentifier::class, $reflection->getParentClass()->getName());
    }

    /**
     * The standing PF-044 rule: a concrete identifier is an **empty** marker
     * subclass. PHP cannot enforce it, so this test does.
     */
    public function test_firm_id_declares_no_member_of_its_own(): void
    {
        $reflection = new \ReflectionClass(FirmId::class);

        $this->assertSame([], $this->ownMethodNames($reflection), 'FirmId must declare no method.');
        $this->assertSame([], $this->ownPropertyNames($reflection), 'FirmId must declare no property.');
        $this->assertSame([], $this->ownConstantNames($reflection), 'FirmId must declare no constant.');

        // No constructor and no factory alias of its own: construction stays
        // BusinessIdentifier's `generate()` and `fromString()`, inherited whole.
        $this->assertSame(
            BusinessIdentifier::class,
            $reflection->getConstructor()?->getDeclaringClass()->getName(),
            'FirmId must declare no constructor of its own.',
        );

        // Canonicalized: PHP reports inherited interfaces in resolution order,
        // which differs between a base and its leaf without either implementing
        // anything extra. The *set* is what must be identical.
        $this->assertEqualsCanonicalizing(
            (new \ReflectionClass(BusinessIdentifier::class))->getInterfaceNames(),
            $reflection->getInterfaceNames(),
            'FirmId must add no interface of its own.',
        );
    }

    // --------------------------------------------------------- value objects

    public function test_each_value_object_is_final_readonly_and_implements_value_object(): void
    {
        foreach ([FirmName::class, FirmJurisdiction::class, FirmId::class] as $class) {
            $reflection = new \ReflectionClass($class);

            $this->assertTrue($reflection->isFinal(), $class.' must be final.');
            $this->assertTrue($reflection->isReadOnly(), $class.' must be readonly.');
            $this->assertTrue($reflection->implementsInterface(ValueObject::class), $class.' must implement ValueObject.');
        }
    }

    public function test_firm_name_exposes_exactly_its_approved_public_api(): void
    {
        $this->assertSame(
            ['equals', 'fromString', 'value'],
            $this->publicMethodNames(new \ReflectionClass(FirmName::class)),
            'FirmName must expose exactly its named constructor, its value, and equality.',
        );
    }

    /**
     * The jurisdiction reference is **opaque**. Every accessor named here would
     * assign meaning it deliberately does not carry, and would pre-empt Legal
     * Intelligence's own contract — so their absence is asserted by name as
     * well as by the exact-API assertion above.
     */
    public function test_firm_jurisdiction_exposes_exactly_its_approved_opaque_api(): void
    {
        $reflection = new \ReflectionClass(FirmJurisdiction::class);

        $this->assertSame(
            ['equals', 'fromString', 'value'],
            $this->publicMethodNames($reflection),
            'FirmJurisdiction must expose exactly its named constructor, its value, and equality.',
        );

        $forbidden = [
            'country', 'countryCode', 'region', 'court', 'language', 'languages',
            'tax', 'taxTreatment', 'regulator', 'regulatory', 'parse', 'components',
            'segments', 'segment', 'prefix', 'suffix', 'resolve', 'lookup',
            'jurisdiction', 'isThailand', 'allowList', 'isKnown', 'toIso', 'iso',
        ];

        foreach ($forbidden as $method) {
            $this->assertFalse(
                $reflection->hasMethod($method),
                'FirmJurisdiction must expose no '.$method.'() accessor — it is an opaque reference.',
            );
        }

        $this->assertSame(
            ['MAXIMUM_LENGTH', 'MINIMUM_LENGTH'],
            $this->ownConstantNames($reflection),
            'FirmJurisdiction must hold only its published bounds — no allow-list, seeded list, or country table.',
        );
    }

    public function test_firm_jurisdiction_publishes_the_approved_bounds(): void
    {
        $this->assertSame(2, FirmJurisdiction::MINIMUM_LENGTH);
        $this->assertSame(32, FirmJurisdiction::MAXIMUM_LENGTH);
    }

    public function test_firm_name_publishes_the_approved_bounds(): void
    {
        $this->assertSame(1, FirmName::MINIMUM_LENGTH);
        $this->assertSame(200, FirmName::MAXIMUM_LENGTH);
    }

    // ------------------------------------------------------------- lifecycle

    public function test_firm_lifecycle_state_declares_exactly_the_four_approved_cases(): void
    {
        $this->assertSame(
            ['Requested', 'Provisioned', 'Active', 'Closed'],
            array_map(static fn (FirmLifecycleState $case): string => $case->name, FirmLifecycleState::cases()),
            'The lifecycle vocabulary is exactly Requested, Provisioned, Active, Closed — in that order.',
        );
    }

    public function test_firm_lifecycle_state_is_a_pure_enum_with_no_behaviour(): void
    {
        $reflection = new \ReflectionEnum(FirmLifecycleState::class);

        $this->assertFalse($reflection->isBacked(), 'A backing value would fix PA-007\'s storage representation.');
        $this->assertSame(
            ['cases'],
            $this->publicMethodNames($reflection),
            'FirmLifecycleState must declare no transition, guard, map, or is* helper.',
        );
    }

    /**
     * There is no Firm-level suspended state, and none may be invented.
     */
    public function test_firm_lifecycle_state_declares_no_suspension_case(): void
    {
        $names = array_map(static fn (FirmLifecycleState $case): string => strtolower($case->name), FirmLifecycleState::cases());

        foreach (['suspended', 'disabled', 'locked', 'frozen', 'terminated', 'deleted'] as $forbidden) {
            $this->assertNotContains($forbidden, $names, 'There is no Firm-level '.$forbidden.' state.');
        }
    }

    // ------------------------------------------------------------------- Firm

    public function test_firm_is_a_final_aggregate_root(): void
    {
        $reflection = new \ReflectionClass(Firm::class);

        $this->assertTrue($reflection->isFinal(), 'Firm must be final.');
        $this->assertSame(AggregateRoot::class, $reflection->getParentClass()->getName());
    }

    /**
     * PHPStan needs the generic tag to narrow `id()` to `FirmId`; a missing tag
     * is reported only from level 6, and this project runs level 5.
     */
    public function test_firm_declares_the_generic_aggregate_root_extends_tag(): void
    {
        $this->assertStringContainsString(
            '@extends AggregateRoot<FirmId>',
            (string) (new \ReflectionClass(Firm::class))->getDocComment(),
        );
    }

    public function test_firm_exposes_exactly_its_approved_public_api(): void
    {
        $this->assertSame(
            ['create', 'id', 'jurisdiction', 'lifecycleState', 'name', 'releaseEvents', 'sameIdentityAs'],
            $this->publicMethodNames(new \ReflectionClass(Firm::class)),
            'Firm exposes its creation constructor and read-only accessors, plus the inherited identity and event members.',
        );
    }

    public function test_firm_construction_is_private_so_creation_always_records(): void
    {
        $constructor = (new \ReflectionClass(Firm::class))->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPrivate(), 'Only Firm::create() may produce a Firm.');
    }

    /**
     * **No path can leave `Requested`.** Asserted against the enumerated
     * declared member list — public *and* protected — rather than by probing
     * for a handful of names, so any newly added member fails this test until
     * it is deliberately approved.
     */
    public function test_firm_declares_no_member_that_could_change_its_state(): void
    {
        $reflection = new \ReflectionClass(Firm::class);

        $this->assertSame(
            ['__construct', 'create', 'jurisdiction', 'lifecycleState', 'name'],
            $this->ownMethodNames($reflection),
            'Firm declares exactly its private constructor, its creation constructor, and three read-only accessors.',
        );

        // Nothing protected either: a protected mutator would be as reachable
        // from a subclass as a public one, and `final` is the only reason no
        // subclass can exist. Enumerating both visibilities means neither can
        // be widened without this test failing.
        $protected = array_values(array_filter(
            $this->ownMethodNames($reflection),
            static fn (string $method): bool => (new \ReflectionMethod(Firm::class, $method))->isProtected(),
        ));

        $this->assertSame([], $protected, 'Firm declares no protected member.');
    }

    /**
     * Named absence: every member the contract says must not exist, checked by
     * name in addition to the exact-list assertion above.
     */
    public function test_firm_declares_no_transition_setter_version_persistence_or_authorization_member(): void
    {
        $reflection = new \ReflectionClass(Firm::class);

        $forbidden = [
            // transitions and mutation
            'provision', 'activate', 'close', 'suspend', 'restore', 'reopen', 'cancel',
            'transitionTo', 'changeState', 'setLifecycleState', 'withLifecycleState',
            'rename', 'setName', 'withName', 'setJurisdiction', 'withJurisdiction', 'setId',
            // version and persistence
            'version', 'aggregateVersion', 'expectedVersion', 'sequenceNumber', 'concurrencyToken',
            'save', 'persist', 'delete', 'flush', 'toArray', 'toRecord', 'fromRecord', 'reconstitute',
            // serialization
            '__toString', 'jsonSerialize', '__serialize', '__unserialize', '__sleep', '__wakeup', '__set',
            // authorization
            'authorize', 'can', 'cannot', 'may', 'checkAccess', 'isAuthorized',
        ];

        foreach ($forbidden as $method) {
            $this->assertFalse($reflection->hasMethod($method), 'Firm must declare no '.$method.'() member.');
        }
    }

    public function test_firm_holds_exactly_its_three_non_identity_attributes(): void
    {
        $reflection = new \ReflectionClass(Firm::class);

        $this->assertSame(
            ['jurisdiction', 'lifecycleState', 'name'],
            $this->ownPropertyNames($reflection),
            'Firm holds exactly name, jurisdiction, and lifecycle state beyond its inherited identity.',
        );

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== Firm::class) {
                continue;
            }

            $this->assertTrue($property->isReadOnly(), $property->getName().' must be readonly.');
            $this->assertTrue($property->isPrivate(), $property->getName().' must be private.');
        }
    }

    /**
     * The aggregate reads no ambient time and no ambient randomness, so it
     * holds neither collaborator — both the instant and the event identifier
     * are supplied as parameters.
     */
    public function test_firm_holds_no_clock_and_no_generator(): void
    {
        $types = [];

        foreach ((new \ReflectionClass(Firm::class))->getProperties() as $property) {
            $type = $property->getType();

            if ($type instanceof \ReflectionNamedType) {
                $types[] = $type->getName();
            }
        }

        $this->assertNotContains('App\Foundation\Domain\Time\Clock', $types);
        $this->assertNotContains('App\Foundation\Domain\Identity\UuidV7Generator', $types);
    }

    // ----------------------------------------------------------- FirmCreated

    public function test_firm_created_is_a_final_readonly_domain_event(): void
    {
        $reflection = new \ReflectionClass(FirmCreated::class);

        $this->assertTrue($reflection->isFinal(), 'FirmCreated must be final.');
        $this->assertTrue($reflection->isReadOnly(), 'FirmCreated must be readonly.');
        $this->assertSame(
            [DomainEvent::class],
            array_values($reflection->getInterfaceNames()),
            'FirmCreated must implement exactly DomainEvent and nothing else.',
        );
        $this->assertFalse($reflection->getParentClass(), 'FirmCreated must extend nothing.');
    }

    public function test_firm_created_exposes_exactly_its_approved_payload_api(): void
    {
        $this->assertSame(
            ['__construct', 'eventId', 'firmId', 'firmJurisdiction', 'firmName', 'lifecycleState', 'occurredAt'],
            $this->publicMethodNames(new \ReflectionClass(FirmCreated::class)),
            'FirmCreated exposes exactly its occurrence identity, its instant, and its four payload values.',
        );
    }

    /**
     * Identity and time are constructor data, never self-generated.
     */
    public function test_firm_created_receives_its_identity_and_instant_as_constructor_data(): void
    {
        $constructor = (new \ReflectionClass(FirmCreated::class))->getConstructor();

        $this->assertNotNull($constructor);

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $parameters[$parameter->getName()] = $type instanceof \ReflectionNamedType ? $type->getName() : null;
        }

        $this->assertSame([
            'eventId' => 'App\Foundation\Domain\Identity\UuidV7',
            'occurredAt' => 'DateTimeImmutable',
            'firmId' => FirmId::class,
            'firmName' => FirmName::class,
            'firmJurisdiction' => FirmJurisdiction::class,
            'lifecycleState' => FirmLifecycleState::class,
        ], $parameters);
    }

    /**
     * The payload carries identifiers and immutable values only — never an
     * `Entity`, an aggregate reference, or a mutable object.
     */
    public function test_firm_created_carries_no_entity_or_aggregate_reference(): void
    {
        foreach ((new \ReflectionClass(FirmCreated::class))->getProperties() as $property) {
            $type = $property->getType();

            $this->assertInstanceOf(\ReflectionNamedType::class, $type);
            $this->assertNotSame(Firm::class, $type->getName(), 'A domain event never carries its aggregate.');
            $this->assertTrue($property->isReadOnly(), $property->getName().' must be readonly.');

            if ($type->isBuiltin()) {
                continue;
            }

            $this->assertFalse(
                is_subclass_of($type->getName(), Entity::class),
                'A domain event never carries an Entity.',
            );
        }
    }

    /**
     * No actor is recorded, and none is fabricated — no authoritative actor
     * exists yet.
     */
    public function test_firm_created_records_no_actor(): void
    {
        $reflection = new \ReflectionClass(FirmCreated::class);

        foreach (['actor', 'actorId', 'principal', 'principalId', 'performedBy', 'createdBy', 'userId'] as $member) {
            $this->assertFalse($reflection->hasMethod($member), 'FirmCreated must record no '.$member.'.');
            $this->assertFalse($reflection->hasProperty($member), 'FirmCreated must record no '.$member.'.');
        }
    }

    // ------------------------------------------------------------- utilities

    /**
     * Every PHP source file under `app/Modules`, keyed by its path relative to
     * that directory, mapped to its contents.
     *
     * @return array<string, string>
     */
    private function moduleSourceFiles(): array
    {
        $root = $this->modulesRoot();

        $this->assertDirectoryExists($root);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            $this->assertIsString($contents, 'Unable to read '.$file->getPathname().'.');

            $files[ltrim(str_replace($root, '', $file->getPathname()), '/')] = $contents;
        }

        ksort($files);

        return $files;
    }

    private function modulesRoot(): string
    {
        return \dirname(__DIR__, 5).'/app/Modules';
    }

    /**
     * The names of the methods a class declares itself, sorted.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function ownMethodNames(\ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $reflection->getName()) {
                $names[] = $method->getName();
            }
        }

        sort($names);

        return $names;
    }

    /**
     * The names of every public method reachable on a class, inherited
     * included, sorted.
     *
     * @param  \ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function publicMethodNames(\ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $names[] = $method->getName();
        }

        sort($names);

        return $names;
    }

    /**
     * @param  \ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function ownPropertyNames(\ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                $names[] = $property->getName();
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @param  \ReflectionClass<object>  $reflection
     * @return list<string>
     */
    private function ownConstantNames(\ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() === $reflection->getName() && $constant->isPublic()) {
                $names[] = $constant->getName();
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Every prohibited Laravel global-helper call in a PHP source string.
     *
     * Detection is token-aware rather than textual, because a helper name in
     * source text is not by itself a call to that helper. A name counts only
     * when it resolves to the global function, an argument list follows, and
     * the preceding significant token does not make it a declaration, an
     * object or static method, or an instantiation. Comments, docblocks, and
     * quoted strings never produce these token types and are ignored for free.
     *
     * @return list<string> the helper names called, in source order
     */
    private function globalHelperCalls(string $source): array
    {
        $tokens = array_values(array_filter(
            token_get_all($source),
            static fn (array|string $token): bool => ! \is_array($token)
                || ! \in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true),
        ));

        $calls = [];

        foreach ($tokens as $index => $token) {
            if (! \is_array($token)) {
                continue;
            }

            $name = $this->globalFunctionName($token);

            if ($name === null || ! \in_array($name, self::FORBIDDEN_GLOBAL_HELPERS, true)) {
                continue;
            }

            if (($tokens[$index + 1] ?? null) !== '(') {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;

            if (\is_array($previous) && \in_array($previous[0], [
                T_FUNCTION,
                T_OBJECT_OPERATOR,
                T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON,
                T_NEW,
            ], true)) {
                continue;
            }

            $calls[] = $name;
        }

        return $calls;
    }

    /**
     * The global function a name token refers to, or null if it names
     * something other than a global function.
     *
     * @param  array{0: int, 1: string, 2: int}  $token
     */
    private function globalFunctionName(array $token): ?string
    {
        if ($token[0] === T_STRING) {
            return strtolower($token[1]);
        }

        if ($token[0] === T_NAME_FULLY_QUALIFIED) {
            $name = ltrim($token[1], '\\');

            return str_contains($name, '\\') ? null : strtolower($name);
        }

        return null;
    }

    /**
     * Strip comments and docblocks, so the dependency guards inspect actual
     * code and documentation prose can never trip them.
     */
    private function withoutComments(string $source): string
    {
        $stripped = '';

        foreach (token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $stripped .= \is_array($token) ? $token[1] : $token;
        }

        return $stripped;
    }
}
