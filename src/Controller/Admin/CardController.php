<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use MajesticDev\ForumifyIdCard\Form\CardType;
use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\{CardIssuer, CardRenderer, CardSettings, CardStatusResolver, CommandNetCardProvider, ExpirationCalculator, MilhqCardProvider, PhotoStorage};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/id-cards')]
class CardController extends AbstractController
{
    public function __construct(private readonly IdentificationCardRepository $cards, private readonly CardIssuer $issuer, private readonly CardRenderer $renderer, private readonly CardSettings $settings, private readonly MilhqCardProvider $milhq, private readonly CommandNetCardProvider $commandNet, private readonly PhotoStorage $photos) {}

    #[Route('', name: 'id_cards_list', methods: ['GET'])]
    #[IsGranted('id-cards.admin.id_cards.view')]
    public function index(Request $request, CardStatusResolver $statuses): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $filters = ['q' => mb_substr($request->query->getString('q'), 0, 100), 'source' => $request->query->getString('source'), 'status' => $request->query->getString('status')];
        $result = $this->cards->search($filters['q'], $filters['source'], $filters['status'], $page);
        return $this->render('@ForumifyIdCardPlugin/admin/list.html.twig', $result + ['page' => $page, 'filters' => $filters, 'statuses' => $statuses, 'photos' => $this->photos, 'commandNetAvailable' => $this->commandNet->isAvailable()]);
    }

    #[Route('/create', name: 'id_cards_create', methods: ['GET', 'POST'])]
    #[IsGranted('id-cards.admin.id_cards.create')]
    public function create(Request $request): Response
    {
        $card = new IdentificationCard();
        $card->memberId = $this->issuer->allocate();
        $settings = $this->settings->all();
        foreach (['organizationLine1', 'organizationLine2', 'organizationLine3'] as $field) { $card->$field = $settings[$field]; }
        $card->expirationDate = (new ExpirationCalculator())->calculate($card->issueDate, (int) $settings['years']);
        return $this->editor($request, $card);
    }

    #[Route('/{id}/edit', name: 'id_cards_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('id-cards.admin.id_cards.manage')]
    public function edit(Request $request, IdentificationCard $card): Response { return $this->editor($request, $card); }

    private function editor(Request $request, IdentificationCard $card): Response
    {
        $oldId = $card->memberId;
        $oldSource = $card->source;
        $oldSoldier = $card->milhqSoldierId;
        $oldCommandNetSoldier = $card->commandNetSoldierId;
        $form = $this->createForm(CardType::class, $card);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if (!$card->expirationOverride) { $card->expirationDate = (new ExpirationCalculator())->calculate($card->issueDate, (int) $this->settings->all()['years']); }
            else { $card->expirationDate = $card->expirationDate->setTime(23, 59, 59); }
            if ($card->id !== null && $oldId !== $card->memberId && !$form->get('confirmMemberIdChange')->getData()) {
                $form->addError(new FormError('Confirm the Member ID change before saving an issued card.'));
            }
            if ($card->source === 'milhq' && (!$card->milhqSoldierId || !$this->milhq->getSoldier($card->milhqSoldierId))) {
                if ($oldSource !== 'milhq' || $oldSoldier !== $card->milhqSoldierId || $card->id === null) {
                    $form->addError(new FormError('Select an available MILHQ soldier.'));
                } else { $card->autoSyncMilhq = false; $this->addFlash('warning', 'MILHQ record unavailable. Sync disabled; existing data preserved.'); }
            }
            if ($card->source === 'commandnet' && (!$card->commandNetSoldierId || !$this->commandNet->getSoldier($card->commandNetSoldierId))) {
                if ($oldSource !== 'commandnet' || $oldCommandNetSoldier !== $card->commandNetSoldierId || $card->id === null) {
                    $form->addError(new FormError('Select an available Command Net soldier.'));
                } else { $card->autoSyncCommandNet = false; $this->addFlash('warning', 'Command Net record unavailable. Sync disabled; existing data preserved.'); }
            }
            if ($form->isValid()) {
                try {
                    $preference = $form->get('photoPreference')->getData();
                    $upload = $form->get('upload')->getData();
                    if ($card->source === 'milhq' && $card->milhqSoldierId && ($card->id === null || $oldSoldier !== $card->milhqSoldierId)) {
                        $resolved = $this->milhq->resolveCardData($card->milhqSoldierId);
                        if ($preference === 'keep' && $card->photoSource !== 'custom') { $card->photo = $resolved->photo; $card->photoSource = $resolved->photoSource; }
                        if ($card->syncStatus && $resolved->status === 'revoked') { $card->revoke('MILHQ personnel status changed.'); }
                    }
                    if ($card->source === 'commandnet' && $card->commandNetSoldierId && ($card->id === null || $oldCommandNetSoldier !== $card->commandNetSoldierId)) {
                        $resolved = $this->commandNet->resolveCardData($card->commandNetSoldierId);
                        if ($preference === 'keep' && $card->photoSource !== 'custom') { $card->photo = $resolved->photo; $card->photoSource = $resolved->photoSource; }
                        if ($card->syncStatus && $resolved->status === 'revoked') { $card->revoke('Command Net personnel status changed.'); }
                    }
                    if ($preference === 'custom' && !$upload && $card->photoSource !== 'custom') { throw new \DomainException('Choose a photo file for the custom photo source.'); }
                    if ($upload) { $card->photo = $this->photos->upload($upload); $card->photoSource = 'custom'; }
                    elseif ($preference === 'default') { $card->photo = null; $card->photoSource = 'default'; }
                    elseif ($preference === 'milhq_uniform') {
                        if ($card->source !== 'milhq' || !$card->milhqSoldierId) { throw new \DomainException('Select a MILHQ soldier for that photo source.'); }
                        $data = $this->milhq->resolveCardData($card->milhqSoldierId, $preference);
                        $card->photo = $data->photo; $card->photoSource = $data->photoSource;
                    } elseif ($preference === 'forumify_avatar') {
                        $data = match ($card->source) {
                            'milhq' => $card->milhqSoldierId ? $this->milhq->resolveCardData($card->milhqSoldierId, $preference) : throw new \DomainException('Select a MILHQ soldier for that photo source.'),
                            'commandnet' => $card->commandNetSoldierId ? $this->commandNet->resolveCardData($card->commandNetSoldierId, $preference) : throw new \DomainException('Select a Command Net soldier for that photo source.'),
                            default => throw new \DomainException('Select a linked personnel record for that photo source.'),
                        };
                        $card->photo = $data->photo; $card->photoSource = $data->photoSource;
                    }
                    if ($card->source !== 'milhq') { $card->milhqSoldierId = null; $card->autoSyncMilhq = false; }
                    if ($card->source !== 'commandnet') { $card->commandNetSoldierId = null; $card->autoSyncCommandNet = false; }
                    $this->issuer->save($card);
                    $this->addFlash('success', 'Card saved.');
                    return $this->redirectToRoute('id_cards_view', ['id' => $card->id]);
                } catch (\DomainException $e) { $form->addError(new FormError($e->getMessage())); }
            }
        }
        return $this->render('@ForumifyIdCardPlugin/admin/editor.html.twig', ['form' => $form->createView(), 'card' => $card, 'data' => $this->renderer->data($card), 'milhqAvailable' => $this->milhq->isAvailable(), 'commandNetAvailable' => $this->commandNet->isAvailable() || $card->source === 'commandnet', 'years' => $this->settings->all()['years']]);
    }

    #[Route('/{id}', name: 'id_cards_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('id-cards.admin.id_cards.view')]
    public function view(IdentificationCard $card): Response
    {
        return $this->render('@ForumifyIdCardPlugin/admin/view.html.twig', ['card' => $card, 'data' => $this->renderer->data($card)]);
    }

    #[Route('/{id}/download', name: 'id_cards_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('id-cards.admin.id_cards.download')]
    public function download(IdentificationCard $card): Response
    {
        return $this->render('@ForumifyIdCardPlugin/admin/download.html.twig', ['card' => $card, 'data' => $this->renderer->data($card)]);
    }

    #[Route('/new-member-id', name: 'id_cards_new_id', methods: ['POST'])]
    public function newId(Request $request): Response
    {
        if (!$this->isGranted('id-cards.admin.id_cards.create') && !$this->isGranted('id-cards.admin.id_cards.manage')) { throw $this->createAccessDeniedException(); }
        $this->csrf($request, 'id_card_editor');
        return $this->json(['memberId' => $this->issuer->allocate()]);
    }

    #[Route('/{id}/sync', name: 'id_cards_sync', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('id-cards.admin.id_cards.manage')]
    public function sync(Request $request, IdentificationCard $card): Response
    {
        $this->csrf($request, 'card_'.$card->id);
        try {
            $warning = $card->source === 'commandnet' ? $this->commandNet->syncCard($card) : $this->milhq->syncCard($card);
            $this->issuer->save($card);
            $this->addFlash($warning ? 'warning' : 'success', $warning ?? 'Card synchronized.');
        } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute('id_cards_view', ['id' => $card->id]);
    }

    #[Route('/{id}/revoke', name: 'id_cards_revoke', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('id-cards.admin.id_cards.revoke')]
    public function revoke(Request $request, IdentificationCard $card): Response
    {
        $this->csrf($request, 'card_'.$card->id);
        if ($request->request->get('confirm') !== 'yes') { throw $this->createAccessDeniedException('Revocation confirmation required.'); }
        $card->revoke(mb_substr($request->request->getString('reason'), 0, 500));
        $this->issuer->save($card);
        return $this->redirectToRoute('id_cards_view', ['id' => $card->id]);
    }

    #[Route('/{id}/delete', name: 'id_cards_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('id-cards.admin.id_cards.delete')]
    public function delete(Request $request, IdentificationCard $card, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'card_'.$card->id);
        if ($request->request->get('confirm') !== 'yes') { throw $this->createAccessDeniedException('Deletion confirmation required.'); }
        $em->remove($card); $em->flush();
        return $this->redirectToRoute('id_cards_list');
    }

    private function csrf(Request $request, string $key): void
    {
        if (!$this->isCsrfTokenValid($key, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Invalid CSRF token.'); }
    }
}
