<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Tests;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\DriverManager;
use MajesticDev\ForumifyIdCard\Entity\{IdentificationCard, UnitMapping};
use MajesticDev\ForumifyIdCard\Service\{MemberIdGenerator, ExpirationCalculator, CardStatusResolver, MilhqCardProvider, CommandNetCardProvider, CardSettings, QrCodeGenerator};
use PHPUnit\Framework\TestCase;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\NullLogger;

class DomainTest extends TestCase
{
    public function testIdsRetryAndPreserveLeadingZeros(): void
    {
        $values = [123456, 6592];
        $id = (new MemberIdGenerator())->generate(fn ($id) => $id === '123456', function () use (&$values) { return array_shift($values); });
        self::assertSame('006592', $id);
        for ($i=0;$i<100;++$i) { self::assertMatchesRegularExpression('/^\d{6}$/D', (new MemberIdGenerator())->generate(fn () => false)); }
    }

    public function testAnniversariesAndEndOfDay(): void
    {
        $calc = new ExpirationCalculator();
        self::assertSame('2031-09-14 23:59:59', $calc->calculate(new \DateTimeImmutable('2026-09-14'))->format('Y-m-d H:i:s'));
        self::assertSame('2029-02-28', $calc->calculate(new \DateTimeImmutable('2024-02-29'))->format('Y-m-d'));
    }

    public function testStatusPrecedence(): void
    {
        $card = new IdentificationCard(); $card->expirationDate = new \DateTimeImmutable('2031-09-14 23:59:59');
        $resolver = new CardStatusResolver();
        self::assertSame('active', $resolver->resolve($card, new \DateTimeImmutable('2031-09-14 23:59:59')));
        self::assertSame('expired', $resolver->resolve($card, new \DateTimeImmutable('2031-09-15')));
        $card->revoke('Test'); self::assertSame('revoked', $resolver->resolve($card, new \DateTimeImmutable('2032-01-01')));
    }

    public function testOptionalMilhqAndTokenEntropy(): void
    {
        $provider = new MilhqCardProvider($this->createMock(ManagerRegistry::class), $this->createMock(CardSettings::class));
        self::assertFalse($provider->isAvailable()); self::assertSame([], $provider->searchSoldiers('test'));
        $a = new IdentificationCard(); $b = new IdentificationCard();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $a->qrToken); self::assertNotSame($a->qrToken, $b->qrToken);
        self::assertSame('manual', $a->source);
    }

    public function testQrIsPngAndContainsOpaqueUrl(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $card = new IdentificationCard();
        $router->method('generate')->willReturn('https://example.com/id/'.$card->qrToken);
        $settings = $this->createMock(CardSettings::class); $settings->method('all')->willReturn(CardSettings::DEFAULTS);
        $qr = new QrCodeGenerator($router, $settings);
        self::assertStringContainsString($card->qrToken, $qr->url($card));
        self::assertSame("\x89PNG\r\n\x1a\n", substr($qr->png($card), 0, 8));
    }

    public function testMigrationMatchesEntitySchemaAndPersistsManualCard(): void
    {
        require_once dirname(__DIR__).'/migrations/Version20260914000000.php';
        require_once dirname(__DIR__).'/migrations/Version20260916000000.php';
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__).'/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $em = new EntityManager($connection, $config);
        $schema = new \Doctrine\DBAL\Schema\Schema();
        // Both migrations have to be applied to the same Schema before comparing against the
        // entity mapping - a schema test that only runs the first migration would keep
        // passing even if the Command Net columns drifted from the entity.
        (new \MajesticDevIdCardMigrations\Version20260914000000($connection, new NullLogger()))->up($schema);
        (new \MajesticDevIdCardMigrations\Version20260916000000($connection, new NullLogger()))->up($schema);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) { $connection->executeStatement($sql); }
        $metadata = [$em->getClassMetadata(IdentificationCard::class), $em->getClassMetadata(UnitMapping::class)];
        self::assertSame([], (new SchemaTool($em))->getUpdateSchemaSql($metadata));
        $card = new IdentificationCard(); $card->displayName='Majestic44';$card->memberId='006592';
        $em->persist($card);$em->flush();$em->clear();
        self::assertSame('Majestic44', $em->find(IdentificationCard::class, $card->id)->displayName);

        $cnCard = new IdentificationCard();
        $cnCard->source = 'commandnet';
        $cnCard->commandNetSoldierId = 42;
        $cnCard->autoSyncCommandNet = true;
        $cnCard->displayName = 'CN Soldier';
        $cnCard->memberId = '006593';
        $em->persist($cnCard); $em->flush(); $em->clear();
        $reloaded = $em->find(IdentificationCard::class, $cnCard->id);
        self::assertSame(42, $reloaded->commandNetSoldierId);
        self::assertTrue($reloaded->autoSyncCommandNet);
    }

    public function testOptionalCommandNetAndTerminalStatusOnly(): void
    {
        $provider = new CommandNetCardProvider($this->createMock(ManagerRegistry::class), $this->createMock(CardSettings::class));
        self::assertFalse($provider->isAvailable());
        self::assertSame([], $provider->searchSoldiers('test'));
    }

    public function testCommandNetFallsBackToUnitNameWithNoMappingTableAndNeverWarns(): void
    {
        $unit = new class { public function getName(): string { return 'Misfit - 1 C'; } };
        $assignment = new class($unit) {
            public function __construct(private object $unit) {}
            public function getUnit(): object { return $this->unit; }
        };
        $user = new class {
            public function getDisplayName(): string { return 'Member'; }
            public function getAvatar(): ?string { return null; }
        };
        $status = new class { public string $value = 'active'; };
        $soldier = new class($assignment, $user, $status) {
            public function __construct(private object $assignment, private object $user, private object $status) {}
            public function getPrimaryAssignment(): object { return $this->assignment; }
            public function getUser(): object { return $this->user; }
            public function getStatus(): object { return $this->status; }
        };

        $settings = $this->createMock(CardSettings::class);
        $settings->method('all')->willReturn(CardSettings::DEFAULTS);
        $provider = $this->getMockBuilder(CommandNetCardProvider::class)
            ->setConstructorArgs([$this->createMock(ManagerRegistry::class), $settings])
            ->onlyMethods(['getSoldier'])
            ->getMock();
        $provider->method('getSoldier')->willReturn($soldier);

        $data = $provider->resolveCardData(1);
        self::assertSame(
            [CardSettings::DEFAULTS['organizationLine1'], CardSettings::DEFAULTS['organizationLine2'], 'Misfit - 1 C'],
            $data->organization,
        );
        // Unlike MILHQ, Command Net has no unit-mapping table to warn about missing - it
        // should never populate a warning at all.
        self::assertNull($data->warning);
        self::assertNull($data->status);
    }

    public function testCommandNetDischargedIsTheOnlyTerminalStatus(): void
    {
        $unit = new class { public function getName(): string { return 'HQ'; } };
        $assignment = new class($unit) {
            public function __construct(private object $unit) {}
            public function getUnit(): object { return $this->unit; }
        };
        $user = new class {
            public function getDisplayName(): string { return 'Member'; }
            public function getAvatar(): ?string { return null; }
        };
        $settings = $this->createMock(CardSettings::class);
        $settings->method('all')->willReturn(CardSettings::DEFAULTS);

        foreach (['discharged' => 'revoked', 'awol' => null, 'active' => null] as $label => $expected) {
            $status = new class($label) {
                public function __construct(public string $value) {}
            };
            $soldier = new class($assignment, $user, $status) {
                public function __construct(private object $assignment, private object $user, private object $status) {}
                public function getPrimaryAssignment(): object { return $this->assignment; }
                public function getUser(): object { return $this->user; }
                public function getStatus(): object { return $this->status; }
            };
            $provider = $this->getMockBuilder(CommandNetCardProvider::class)
                ->setConstructorArgs([$this->createMock(ManagerRegistry::class), $settings])
                ->onlyMethods(['getSoldier'])
                ->getMock();
            $provider->method('getSoldier')->willReturn($soldier);
            self::assertSame($expected, $provider->resolveCardData(1)->status, "status for '$label'");
        }
    }

    public function testCommandNetSyncPreservesOwnedFieldsAndCustomPhoto(): void
    {
        $card = new IdentificationCard();
        $card->source = 'commandnet';
        $card->commandNetSoldierId = 7;
        $card->memberId = '006592';
        $card->photoSource = 'custom';
        $card->photo = 'custom.png';
        $before = [$card->memberId, $card->issueDate, $card->expirationDate, $card->qrToken];

        $provider = $this->getMockBuilder(CommandNetCardProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolveCardData'])
            ->getMock();
        $provider->method('resolveCardData')->willReturn(
            new \MajesticDev\ForumifyIdCard\DTO\CardData('New name', ['A', 'B', 'C'], 'avatar.png', 'forumify_avatar', 'revoked'),
        );
        $card->syncStatus = true;
        $provider->syncCard($card);

        self::assertSame('New name', $card->displayName);
        self::assertSame('C', $card->organizationLine3);
        self::assertSame('custom.png', $card->photo);
        self::assertSame('revoked', $card->status);
        self::assertSame($before, [$card->memberId, $card->issueDate, $card->expirationDate, $card->qrToken]);
    }

    public function testCommandNetSyncCardIsANoOpForOtherSources(): void
    {
        $card = new IdentificationCard();
        $card->source = 'milhq';
        $card->milhqSoldierId = 3;

        $provider = $this->getMockBuilder(CommandNetCardProvider::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolveCardData'])
            ->getMock();
        $provider->expects(self::never())->method('resolveCardData');
        self::assertNull($provider->syncCard($card));

        $card2 = new IdentificationCard();
        $card2->source = 'commandnet';
        $card2->commandNetSoldierId = null;
        self::assertNull($provider->syncCard($card2));
    }

    public function testSyncPreservesOwnedFieldsAndCustomPhoto(): void
    {
        $card = new IdentificationCard();$card->source='milhq';$card->milhqSoldierId=7;$card->memberId='006592';$card->photoSource='custom';$card->photo='custom.png';
        $before = [$card->memberId,$card->issueDate,$card->expirationDate,$card->qrToken];
        $provider=$this->getMockBuilder(MilhqCardProvider::class)->disableOriginalConstructor()->onlyMethods(['resolveCardData'])->getMock();
        $provider->method('resolveCardData')->willReturn(new \MajesticDev\ForumifyIdCard\DTO\CardData('New name',['A','B','C'],'uniform.png','milhq_uniform','revoked'));
        $card->syncStatus=true;$provider->syncCard($card);
        self::assertSame('New name',$card->displayName);self::assertSame('C',$card->organizationLine3);self::assertSame('custom.png',$card->photo);self::assertSame('revoked',$card->status);
        self::assertSame($before,[$card->memberId,$card->issueDate,$card->expirationDate,$card->qrToken]);
    }

    public function testUnitMappingResolvesOrganizationAndNeverReadsRank(): void
    {
        $unit = new class { public function getId(): int { return 9; } public function getName(): string { return 'Misfit - 1 A'; } };
        $soldier = new class($unit) {
            public function __construct(private object $unit) {}
            public function getName(): string { return 'Member'; }
            public function getUnit(): object { return $this->unit; }
            public function getUniform(): ?string { return null; }
            public function getUser(): ?object { return null; }
            public function getStatus(): ?object { return null; }
        };
        $mapping = new UnitMapping(); $mapping->organizationLine3='Misfit - 1 A';
        $repo=$this->createMock(ObjectRepository::class);$repo->expects(self::once())->method('findOneBy')->with(['milhqUnitId'=>9,'enabled'=>true])->willReturn($mapping);
        $registry=$this->createMock(ManagerRegistry::class);$registry->method('getRepository')->willReturn($repo);
        $settings=$this->createMock(CardSettings::class);$settings->method('all')->willReturn(CardSettings::DEFAULTS);
        $provider=$this->getMockBuilder(MilhqCardProvider::class)->setConstructorArgs([$registry,$settings])->onlyMethods(['getSoldier'])->getMock();
        $provider->method('getSoldier')->willReturn($soldier);
        self::assertSame(['2nd Ranger Battalion','Misfit Company','Misfit - 1 A'],$provider->resolveCardData(1)->organization);
    }
}
