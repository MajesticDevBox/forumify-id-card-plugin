<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Controller\Admin;

use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use MajesticDev\ForumifyIdCard\Service\{MilhqCardProvider, PhotoStorage};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

class MilhqController extends AbstractController
{
    #[Route('/admin/id-cards/milhq/search', name: 'id_cards_soldiers', methods: ['GET'])]
    public function search(Request $request, MilhqCardProvider $provider): Response
    {
        $this->guard();
        return $this->json(['available' => $provider->isAvailable(), 'soldiers' => $provider->searchSoldiers($request->query->getString('q'))]);
    }

    #[Route('/admin/id-cards/milhq/{id}', name: 'id_cards_soldier', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function resolve(int $id, MilhqCardProvider $provider, PhotoStorage $photos): Response
    {
        $this->guard();
        try { $data = $provider->resolveCardData($id); }
        catch (\DomainException) { throw $this->createNotFoundException('MILHQ record unavailable.'); }
        $card = new IdentificationCard(); $card->photo = $data->photo; $card->photoSource = $data->photoSource;
        return $this->json(['name' => $data->name, 'organization' => $data->organization, 'photo' => $photos->url($card), 'warning' => $data->warning]);
    }

    private function guard(): void
    {
        if (!$this->isGranted('id-cards.admin.id_cards.create') && !$this->isGranted('id-cards.admin.id_cards.manage')) { throw $this->createAccessDeniedException(); }
    }
}
