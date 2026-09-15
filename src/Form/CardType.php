<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Form;

use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\{ChoiceType, TextType, DateType, CheckboxType, IntegerType, FileType, TextareaType};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CardType extends AbstractType
{
    public function __construct(
        private readonly \MajesticDev\ForumifyIdCard\Service\CardSettings $settings,
        private readonly \MajesticDev\ForumifyIdCard\Service\ExpirationCalculator $expiration,
        private readonly \MajesticDev\ForumifyIdCard\Service\MilhqCardProvider $milhq,
        private readonly \MajesticDev\ForumifyIdCard\Service\CommandNetCardProvider $commandNet,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var IdentificationCard|null $initial */
        $initial = $options['data'] ?? null;
        // Only ever offer a source that's actually configured on this install (or is what
        // an existing card already uses, so editing one never gets stranded if that
        // integration is later removed) - this is what lets the dropdown adapt to whichever
        // personnel plugin a given community runs instead of always listing every source.
        $sourceChoices = ['Manual Entry' => 'manual'];
        if ($this->milhq->isAvailable() || $initial?->source === 'milhq') {
            $sourceChoices['MILHQ Soldier'] = 'milhq';
        }
        $commandNetOffered = $this->commandNet->isAvailable() || $initial?->source === 'commandnet';
        if ($commandNetOffered) {
            $sourceChoices['Command Net Personnel'] = 'commandnet';
        }
        $builder->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SUBMIT, function (\Symfony\Component\Form\FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data) || !empty($data['expirationOverride']) || !is_string($data['issueDate'] ?? null)) { return; }
            $issue = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['issueDate']);
            if ($issue && $issue->format('Y-m-d') === $data['issueDate']) {
                $data['expirationDate'] = $this->expiration->calculate($issue, (int) $this->settings->all()['years'])->format('Y-m-d');
                $event->setData($data);
            }
        });
        $builder->add('source', ChoiceType::class, ['choices' => $sourceChoices])
            ->add('milhqSoldierId', IntegerType::class, ['required' => false, 'label' => 'Selected MILHQ Soldier ID', 'attr' => ['readonly' => true]]);
        if ($commandNetOffered) {
            $builder->add('commandNetSoldierId', IntegerType::class, ['required' => false, 'label' => 'Selected Command Net Soldier ID', 'attr' => ['readonly' => true]]);
        }
        $builder->add('displayName', TextType::class)
            ->add('memberId', TextType::class, ['attr' => ['readonly' => true]])
            ->add('confirmMemberIdChange', CheckboxType::class, ['mapped' => false, 'required' => false, 'label' => 'Confirm changing the issued Member ID'])
            ->add('organizationLine1', TextType::class, ['required' => false, 'empty_data' => ''])
            ->add('organizationLine2', TextType::class, ['required' => false, 'empty_data' => ''])
            ->add('organizationLine3', TextType::class, ['required' => false, 'empty_data' => ''])
            ->add('photoPreference', ChoiceType::class, ['mapped' => false, 'choices' => ['Keep current / automatic fallback' => 'keep', 'Custom Upload' => 'custom', 'MILHQ Uniform' => 'milhq_uniform', 'Forumify Avatar' => 'forumify_avatar', 'Default Placeholder' => 'default']])
            ->add('upload', FileType::class, ['mapped' => false, 'required' => false, 'label' => 'Portrait photo', 'constraints' => [new Assert\Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], minWidth: 80, minHeight: 80, maxPixels: 24000000)], 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']])
            ->add('issueDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('expirationOverride', CheckboxType::class, ['required' => false, 'label' => 'Override calculated expiration'])
            ->add('expirationDate', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('autoSyncMilhq', CheckboxType::class, ['required' => false, 'label' => 'Auto sync from MILHQ']);
        if ($commandNetOffered) {
            $builder->add('autoSyncCommandNet', CheckboxType::class, ['required' => false, 'label' => 'Auto sync from Command Net']);
        }
        $builder->add('syncName', CheckboxType::class, ['required' => false])
            ->add('syncOrganization', CheckboxType::class, ['required' => false])
            ->add('syncPhoto', CheckboxType::class, ['required' => false])
            ->add('syncStatus', CheckboxType::class, ['required' => false, 'help' => $commandNetOffered
                ? 'A terminal personnel status (MILHQ discharged/revoked/terminated, or a Command Net discharge) revokes the card. Other statuses do not change validity.'
                : 'Discharged, revoked or terminated MILHQ status revokes the card. Other labels do not change validity.'])
            ->add('notes', TextareaType::class, ['required' => false, 'help' => 'Administrator notes. Never displayed publicly.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => IdentificationCard::class]);
    }
}
