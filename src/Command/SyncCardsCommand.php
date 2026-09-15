<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Command;

use Doctrine\ORM\EntityManagerInterface;
use MajesticDev\ForumifyIdCard\Entity\IdentificationCard;
use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\CommandNetCardProvider;
use MajesticDev\ForumifyIdCard\Service\MilhqCardProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'id-cards:sync', description: 'Synchronize cards that opted into MILHQ or Command Net auto sync.')]
class SyncCardsCommand extends Command
{
    public function __construct(
        private readonly IdentificationCardRepository $cards,
        private readonly MilhqCardProvider $milhq,
        private readonly CommandNetCardProvider $commandNet,
        private readonly EntityManagerInterface $em,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->milhq->isAvailable() && !$this->commandNet->isAvailable()) {
            $output->writeln('No personnel integration available. Cards preserved.');
            return Command::SUCCESS;
        }
        $count = 0;
        $query = $this->cards->createQueryBuilder('c')
            ->where("(c.source = 'milhq' AND c.autoSyncMilhq = true) OR (c.source = 'commandnet' AND c.autoSyncCommandNet = true)")
            ->getQuery();
        /** @var IdentificationCard $card */
        foreach ($query->toIterable() as $card) {
            try {
                $card->source === 'commandnet' ? $this->commandNet->syncCard($card) : $this->milhq->syncCard($card);
                ++$count;
            } catch (\DomainException) { $output->writeln('A linked personnel record was unavailable; card preserved.'); }
            $this->em->flush();
            $this->em->detach($card);
        }
        $output->writeln(sprintf('Synchronized %d cards.', $count));
        return Command::SUCCESS;
    }
}
