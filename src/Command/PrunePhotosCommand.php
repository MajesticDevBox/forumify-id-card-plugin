<?php

declare(strict_types=1);

namespace MajesticDev\ForumifyIdCard\Command;

use MajesticDev\ForumifyIdCard\Repository\IdentificationCardRepository;
use MajesticDev\ForumifyIdCard\Service\PhotoStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A custom photo is replaced (new upload, switching away from 'custom', or a card getting
 * deleted outright) without ever deleting the old file from public/storage/id-cards/ - only
 * milhq_uniform/forumify_avatar photos reference an external package, so 'custom' is the only
 * source this plugin stores locally and the only one that can orphan a file here. Opt-in and
 * not scheduled anywhere: an admin runs this by hand (or wires their own cron to it).
 */
#[AsCommand(name: 'id-cards:prune-photos', description: 'Delete custom photo files no longer referenced by any card.')]
class PrunePhotosCommand extends Command
{
    public function __construct(
        private readonly IdentificationCardRepository $cards,
        private readonly PhotoStorage $photos,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('delete', null, InputOption::VALUE_NONE, 'Actually delete orphaned files. Without this, only lists what would be deleted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->photos->directory();
        if (!is_dir($dir)) {
            $output->writeln('No photo storage directory yet; nothing to prune.');
            return Command::SUCCESS;
        }

        $inUse = array_flip($this->cards->createQueryBuilder('c')
            ->select('c.photo')
            ->where("c.photoSource = 'custom'")
            ->andWhere('c.photo IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult());

        $orphans = [];
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..' || isset($inUse[$file])) {
                continue;
            }
            $orphans[] = $file;
        }

        if ($orphans === []) {
            $output->writeln('No orphaned photos found.');
            return Command::SUCCESS;
        }

        $delete = (bool) $input->getOption('delete');
        foreach ($orphans as $file) {
            if ($delete) {
                @unlink($dir.'/'.$file);
            }
            $output->writeln(($delete ? 'Deleted ' : 'Would delete ').$file);
        }
        $output->writeln(sprintf(
            '%d orphaned photo%s%s.',
            count($orphans),
            count($orphans) === 1 ? '' : 's',
            $delete ? ' deleted' : ' found - rerun with --delete to remove them',
        ));

        return Command::SUCCESS;
    }
}
