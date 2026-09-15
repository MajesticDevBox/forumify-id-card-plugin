<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use MajesticDev\ForumifyIdCard\Entity\UnitMapping;
use MajesticDev\ForumifyIdCard\Service\{CardSettings, PhotoStorage};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\{TextType, IntegerType, CheckboxType, FileType};
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

class ConfigurationController extends AbstractController
{
    #[Route('/admin/id-cards/settings', name: 'id_cards_settings', methods: ['GET', 'POST'])]
    #[IsGranted('id-cards.admin.id_card_configuration.manage')]
    public function settings(Request $request, CardSettings $settings, PhotoStorage $photos): Response
    {
        $builder = $this->createFormBuilder($settings->all());
        foreach (['organizationLine1', 'organizationLine2', 'organizationLine3', 'header', 'subtitle', 'disclaimer'] as $field) {
            $builder->add($field, TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]]);
        }
        $builder->add('years', IntegerType::class, ['label' => 'Default expiration years', 'constraints' => [new Assert\Range(min: 1, max: 20)]])
            ->add('baseUrl', TextType::class, ['required' => false, 'empty_data' => '', 'label' => 'Canonical verification origin', 'help' => 'Optional https://forum.example.com origin, without a path. Blank uses the current Forumify host.', 'constraints' => [new Assert\Regex(pattern: '~^$|^https://[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$~D')]])
            ->add('uploadLogo', FileType::class, ['mapped' => false, 'required' => false, 'constraints' => [new Assert\Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxPixels: 24000000)]])
            ->add('removeLogo', CheckboxType::class, ['mapped' => false, 'required' => false]);
        $form = $builder->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                if ($form->get('removeLogo')->getData()) { $data['logo'] = null; }
                if ($form->get('uploadLogo')->getData()) { $data['logo'] = $photos->upload($form->get('uploadLogo')->getData()); }
                $settings->save($data);
                $this->addFlash('success', 'Settings saved. Existing issued dates are unchanged.');
                return $this->redirectToRoute('id_cards_settings');
            } catch (\DomainException $e) { $form->addError(new FormError($e->getMessage())); }
        }
        return $this->render('@ForumifyIdCardPlugin/admin/configuration.html.twig', ['title' => 'Card settings', 'form' => $form->createView(), 'mappings' => null]);
    }

    #[Route('/admin/id-cards/unit-mapping', name: 'id_cards_mappings', methods: ['GET', 'POST'])]
    #[IsGranted('id-cards.admin.id_card_configuration.unit_mapping')]
    public function mappings(Request $request, EntityManagerInterface $em): Response
    {
        $mapping = $request->query->getInt('edit') ? $em->find(UnitMapping::class, $request->query->getInt('edit')) : new UnitMapping();
        if (!$mapping) { throw $this->createNotFoundException(); }
        $builder = $this->createFormBuilder($mapping)->add('milhqUnitId', IntegerType::class)->add('milhqUnitName', TextType::class);
        foreach (['organizationLine1', 'organizationLine2', 'organizationLine3'] as $field) { $builder->add($field, TextType::class); }
        $form = $builder->add('enabled', CheckboxType::class, ['required' => false])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $em->getRepository(UnitMapping::class)->findOneBy(['milhqUnitId' => $mapping->milhqUnitId]);
            if ($existing && $existing->id !== $mapping->id) { $form->addError(new FormError('This unit already has a mapping. Edit the existing mapping.')); }
            else {
                try { $em->persist($mapping); $em->flush(); return $this->redirectToRoute('id_cards_mappings'); }
                catch (UniqueConstraintViolationException) { throw $this->createNotFoundException('Mapping changed concurrently. Reload this page.'); }
            }
        }
        return $this->render('@ForumifyIdCardPlugin/admin/configuration.html.twig', ['title' => 'Unit mapping', 'form' => $form->createView(), 'mappings' => $em->getRepository(UnitMapping::class)->findBy([], ['milhqUnitName' => 'ASC'])]);
    }

    #[Route('/admin/id-cards/unit-mapping/{id}/delete', name: 'id_cards_mapping_delete', methods: ['POST'])]
    #[IsGranted('id-cards.admin.id_card_configuration.unit_mapping')]
    public function deleteMapping(Request $request, UnitMapping $mapping, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('mapping_'.$mapping->id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $em->remove($mapping); $em->flush();
        return $this->redirectToRoute('id_cards_mappings');
    }
}
