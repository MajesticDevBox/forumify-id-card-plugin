<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Controller;

use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\CardRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    #[Route('/id/{token}', name: 'id_cards_verify', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function __invoke(
        string $token,
        Request $request,
        IdentificationCardRepository $cards,
        CardRenderer $renderer,
        RateLimiterFactoryInterface $idCardsVerifyLimiter,
    ): Response {
        // Public, unauthenticated route (a QR code scan) - keyed by IP rather than user,
        // since there is no user. 64-hex tokens are already unguessable; this only slows
        // down a scripted scan of many tokens, not a targeted lookup of a known one.
        $limit = $idCardsVerifyLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $response = new Response('Too many requests.', Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));
            return $response;
        }

        $card = $cards->findOneBy(['qrToken' => $token]);
        if (!$card) { throw $this->createNotFoundException(); }
        $response = $this->render('@ForumifyIdCardPlugin/frontend/verify.html.twig', ['data' => $renderer->data($card)]);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
