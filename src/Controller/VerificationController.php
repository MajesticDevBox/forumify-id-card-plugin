<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Controller;

use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\CardRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    #[Route('/id/{token}', name: 'id_cards_verify', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function __invoke(string $token, IdentificationCardRepository $cards, CardRenderer $renderer): Response
    {
        $card = $cards->findOneBy(['qrToken' => $token]);
        if (!$card) { throw $this->createNotFoundException(); }
        $response = $this->render('@ForumifyIdCardPlugin/frontend/verify.html.twig', ['data' => $renderer->data($card)]);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
