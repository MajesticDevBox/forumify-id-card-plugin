<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Doctrine\ORM\Tools\SchemaTool;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;

class HttpTest extends WebTestCase
{
    protected static function getKernelClass(): string { return TestKernel::class; }

    public function testManualCreateVerifyRevokeAndInvalidToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $schema = new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        $crawler=$client->request('GET','/admin/id-cards/create');self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Issue card')->form(['card[displayName]'=>'Majestic44', 'card[notes]'=>'PRIVATE NEVER PUBLIC']));
        self::assertResponseRedirects();
        $client->followRedirect();self::assertResponseIsSuccessful();
        $card=static::getContainer()->get('doctrine')->getRepository(IdentificationCard::class)->findOneBy(['displayName'=>'Majestic44']);
        self::assertNotNull($card);$token=$card->qrToken;$id=$card->id;
        $client->request('GET','/id/'.$token);self::assertResponseIsSuccessful();self::assertSelectorTextContains('h2','Majestic44');self::assertStringNotContainsString('PRIVATE NEVER PUBLIC',$client->getResponse()->getContent());
        $client->request('GET','/id/'.str_repeat('a',64));self::assertResponseStatusCodeSame(404);
        $crawler=$client->request('GET','/admin/id-cards/'.$id);
        $client->submit($crawler->selectButton('Revoke card')->form(['confirm'=>'yes','reason'=>'test']));self::assertResponseRedirects();
        $client->request('GET','/id/'.$token);self::assertSelectorTextContains('.id-status','REVOKED');
        self::assertStringContainsString('no-store',$client->getResponse()->headers->get('Cache-Control'));
    }

    public function testUnauthorizedAdminRequestsAreDenied(): void
    {
        $client=static::createClient();
        foreach (['/admin/id-cards','/admin/id-cards/create','/admin/id-cards/settings','/admin/id-cards/unit-mapping','/admin/id-cards/milhq/search','/admin/id-cards/commandnet/search','/admin/id-cards/create-from-commandnet/1'] as $path) {
            $client->request('GET',$path);self::assertResponseStatusCodeSame(401);
        }
    }

    public function testCommandNetSearchReportsUnavailableWhenNotInstalled(): void
    {
        $client=static::createClient();$client->loginUser(new InMemoryUser('admin','unused',['ROLE_ADMIN']));
        $client->request('GET','/admin/id-cards/commandnet/search',['q'=>'test']);
        self::assertResponseIsSuccessful();
        self::assertSame(['available'=>false,'soldiers'=>[]],json_decode($client->getResponse()->getContent(),true));
    }

    public function testCreateFromCommandNetIs404WhenUnavailableAndDeniedWithoutPermission(): void
    {
        $client=static::createClient();$client->loginUser(new InMemoryUser('admin','unused',['ROLE_ADMIN']));
        $client->request('GET','/admin/id-cards/create-from-commandnet/1');
        self::assertResponseStatusCodeSame(404);

        $client->loginUser(new InMemoryUser('viewer','unused',['ROLE_USER']));
        $client->request('GET','/admin/id-cards/create-from-commandnet/1');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCsrfRejectsRegenerate(): void
    {
        $client=static::createClient();$client->loginUser(new InMemoryUser('admin','unused',['ROLE_ADMIN']));
        $client->request('POST','/admin/id-cards/new-member-id',['_token'=>'bad']);self::assertResponseStatusCodeSame(403);
    }

    public function testIssuedIdChangeNeedsConfirmationAndServerRecalculatesDates(): void
    {
        $client=static::createClient();$client->loginUser(new InMemoryUser('admin','unused',['ROLE_ADMIN']));
        $em=static::getContainer()->get('doctrine')->getManager();
        $card=new IdentificationCard();$card->displayName='Edit test';$card->memberId='000099';$em->persist($card);$em->flush();$id=$card->id;
        $crawler=$client->request('GET','/admin/id-cards/'.$id.'/edit');
        $client->submit($crawler->selectButton('Save changes')->form(['card[memberId]'=>'000098','card[issueDate]'=>'2040-02-29','card[expirationDate]'=>'2000-01-01']));
        self::assertResponseIsSuccessful();self::assertStringContainsString('Confirm the Member ID change',$client->getResponse()->getContent());
        $crawler=$client->getCrawler();$client->submit($crawler->selectButton('Save changes')->form(['card[confirmMemberIdChange]'=>'1']));self::assertResponseRedirects();
        $saved=static::getContainer()->get('doctrine')->getRepository(IdentificationCard::class)->find($id);
        self::assertSame('000098',$saved->memberId);self::assertSame('2045-02-28',$saved->expirationDate->format('Y-m-d'));
    }

    public function testAuthenticatedUserWithoutPermissionIsDenied(): void
    {
        $client=static::createClient();$client->loginUser(new InMemoryUser('viewer','unused',['ROLE_USER']));
        $client->request('GET','/admin/id-cards/create');
        self::assertResponseStatusCodeSame(403);
    }
}

