<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Discord\Command;

use Forumify\Discord\Api\DTO\DiscordCommandOption;
use Forumify\Discord\Api\DTO\DiscordCommandResult;
use Forumify\Discord\Api\DTO\DiscordEmbed;
use Forumify\Discord\Api\Resource\DiscordCommandRun;
use Forumify\Discord\Discord\DiscordCommandInterface;
use Forumify\OAuth\Idp\DiscordIdp;
use Forumify\OAuth\Repository\IdentityProviderUserRepository;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\CardStatusResolver;
use MajesticDev\ForumifyIdCard\Service\CommandNetCardProvider;
use MajesticDev\ForumifyIdCard\Service\MilhqCardProvider;
use MajesticDev\ForumifyIdCard\Service\PhotoStorage;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Same shape as commandnet-plugin's SoldierCommand: self-lookup via linked Discord
 * account, falling back to a name/member-ID search. "Self" is resolved through whichever
 * optional personnel source (Command Net or MILHQ) the card was synced from - a manually
 * issued card with no linked soldier record can only be found by name here.
 */
class IdCardCommand implements DiscordCommandInterface
{
    public function __construct(
        private readonly IdentificationCardRepository $cardRepository,
        private readonly IdentityProviderUserRepository $idpUserRepository,
        private readonly CardStatusResolver $statusResolver,
        private readonly CommandNetCardProvider $commandNetCardProvider,
        private readonly MilhqCardProvider $milhqCardProvider,
        private readonly PhotoStorage $photoStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UrlHelper $urlHelper,
    ) {
    }

    public function getName(): string
    {
        return 'id-card';
    }

    public function getDescription(): string
    {
        return "Shows a member's identification card status.";
    }

    public function getOptions(): array
    {
        return [
            new DiscordCommandOption()
                ->setName('name')
                ->setDescription('Optional name or member ID, if left blank it will show your own card.'),
        ];
    }

    public function run(DiscordCommandRun $command): DiscordCommandResult
    {
        $result = new DiscordCommandResult();

        $card = $this->getCardFromCmd($command);
        if ($card === null) {
            $result->content = "We could not find any identification card matching your request. Try coupling your Discord account in your forum account settings, or provide the `name` option to the command.";
            return $result;
        }

        $result->embeds[] = $this->createEmbed($card);
        return $result;
    }

    private function getCardFromCmd(DiscordCommandRun $command): ?IdentificationCard
    {
        $name = trim($command->options['name'] ?? '');
        if ($name !== '') {
            $found = $this->cardRepository->search($name, '', '', 1)['cards'];
            return reset($found) ?: null;
        }

        $self = $this->idpUserRepository->findOneByExternalIdAndIdpType($command->discordUserId, DiscordIdp::getType());
        if ($self === null) {
            return null;
        }
        $user = $self->getUser();

        $soldierId = $this->commandNetCardProvider->findSoldierIdForUser($user);
        if ($soldierId !== null) {
            $card = $this->cardRepository->findOneBy(['source' => 'commandnet', 'commandNetSoldierId' => $soldierId]);
            if ($card !== null) {
                return $card;
            }
        }

        $soldierId = $this->milhqCardProvider->findSoldierIdForUser($user);
        if ($soldierId !== null) {
            return $this->cardRepository->findOneBy(['source' => 'milhq', 'milhqSoldierId' => $soldierId]);
        }

        return null;
    }

    private function createEmbed(IdentificationCard $card): DiscordEmbed
    {
        $status = $this->statusResolver->resolve($card);

        $embed = new DiscordEmbed(
            title: $card->displayName,
            url: $this->urlGenerator->generate('id_cards_verify', ['token' => $card->qrToken], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $embed->addField('Member ID', $card->memberId, true);
        $embed->addField('Status', ucfirst($status), true);
        $embed->addField('Expires', $card->expirationDate->format('Y-m-d'), true);

        $organization = implode("\n", array_filter([$card->organizationLine1, $card->organizationLine2, $card->organizationLine3]));
        if ($organization !== '') {
            $embed->addField('Organization', $organization);
        }

        $photoUrl = $this->photoStorage->url($card);
        if ($photoUrl !== null) {
            $embed->setThumbnail($this->urlHelper->getAbsoluteUrl($photoUrl));
        }

        return $embed;
    }
}
