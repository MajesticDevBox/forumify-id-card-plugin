<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Controller\Admin;

use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\{CardIssuer, CardSettings, CommandNetCardProvider, ExpirationCalculator, PhotoStorage};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mirrors MilhqController's search/resolve endpoints for the optional Command Net source,
 * plus one extra route: a one-click "Create ID Card" action reached from a Command Net
 * personnel file, so staff don't have to go through the generic create form and search for
 * the soldier they're already looking at.
 */
class CommandNetController extends AbstractController
{
    #[Route('/admin/id-cards/commandnet/search', name: 'id_cards_commandnet_search', methods: ['GET'])]
    public function search(Request $request, CommandNetCardProvider $provider): Response
    {
        $this->guard();
        return $this->json(['available' => $provider->isAvailable(), 'soldiers' => $provider->searchSoldiers($request->query->getString('q'))]);
    }

    #[Route('/admin/id-cards/commandnet/{id}', name: 'id_cards_commandnet_soldier', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function resolve(int $id, CommandNetCardProvider $provider, PhotoStorage $photos): Response
    {
        $this->guard();
        try { $data = $provider->resolveCardData($id); }
        catch (\DomainException) { throw $this->createNotFoundException('Command Net record unavailable.'); }
        $card = new IdentificationCard(); $card->photo = $data->photo; $card->photoSource = $data->photoSource;
        return $this->json(['name' => $data->name, 'organization' => $data->organization, 'photo' => $photos->url($card), 'warning' => $data->warning]);
    }

    #[Route('/admin/id-cards/create-from-commandnet/{soldierId}', name: 'id_cards_create_from_commandnet', requirements: ['soldierId' => '\d+'], methods: ['GET'])]
    public function createFromPersonnelFile(
        int $soldierId,
        CommandNetCardProvider $provider,
        IdentificationCardRepository $cards,
        CardIssuer $issuer,
        CardSettings $settings,
    ): Response {
        if (!$this->isGranted('id-cards.admin.id_cards.create') && !$this->isGranted('id-cards.admin.id_cards.manage')) {
            throw $this->createAccessDeniedException();
        }

        $existing = $cards->findOneBy(['source' => 'commandnet', 'commandNetSoldierId' => $soldierId]);
        if ($existing !== null) {
            return $this->redirectToRoute('id_cards_view', ['id' => $existing->id]);
        }

        try { $data = $provider->resolveCardData($soldierId); }
        catch (\DomainException $e) { throw $this->createNotFoundException($e->getMessage()); }

        $card = new IdentificationCard();
        $card->source = 'commandnet';
        $card->commandNetSoldierId = $soldierId;
        $card->autoSyncCommandNet = true;
        $card->memberId = $issuer->allocate();
        $card->displayName = $data->name;
        [$card->organizationLine1, $card->organizationLine2, $card->organizationLine3] = $data->organization;
        $card->photo = $data->photo;
        $card->photoSource = $data->photoSource;
        $card->expirationDate = (new ExpirationCalculator())->calculate($card->issueDate, (int) $settings->all()['years']);
        $issuer->save($card);

        $this->addFlash('success', 'Card created from the Command Net personnel file.');
        return $this->redirectToRoute('id_cards_view', ['id' => $card->id]);
    }

    private function guard(): void
    {
        if (!$this->isGranted('id-cards.admin.id_cards.create') && !$this->isGranted('id-cards.admin.id_cards.manage')) { throw $this->createAccessDeniedException(); }
    }
}
