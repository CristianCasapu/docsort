<?php

declare(strict_types=1);

namespace OCA\DocSort\Command;

use OCA\DocSort\Service\Organizer;
use OCA\DocSort\Service\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Scan extends Command
{
    public function __construct(private Organizer $organizer, private Settings $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('docsort:scan')
            ->setDescription('Read the documents in the inbox folders, say what they are and file them (everybody who switched sorting on, or one person)')
            ->addArgument('user', InputArgument::OPTIONAL, 'one user id (default: everybody with sorting on)')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'only say what would happen')
            ->addOption('again', 'a', InputOption::VALUE_NONE, 'forget earlier decisions and read everything again')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'stop after this many files (0 = all)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $input->getArgument('user') ? [(string) $input->getArgument('user')] : $this->settings->enabledUsers();
        if ([] === $users) {
            $output->writeln('Nobody has switched sorting on (Personal settings › Document sorter).');

            return 0;
        }
        $dry = (bool) $input->getOption('dry-run');
        foreach ($users as $uid) {
            if ((bool) $input->getOption('again') && !$dry) {
                $output->writeln($uid.': forgot '.$this->organizer->forget($uid).' earlier decisions');
            }
            $output->writeln('<info>'.$uid.'</info>'.($dry ? ' (dry run)' : ''));
            $stats = $this->organizer->scanUser($uid, $dry, (int) $input->getOption('limit'), static function (array $r) use ($output): void {
                $what = null !== $r['error'] ? '<error>error: '.$r['error'].'</error>' : (null === $r['kind'] ? 'unknown (score '.$r['score'].')' : $r['kind'].' ('.$r['category'].', score '.$r['score'].', '.$r['engine'].')');
                $output->writeln(sprintf('  %-50s %s%s', mb_substr($r['name'], 0, 50), $what, null !== $r['movedTo'] ? ' → '.$r['movedTo'] : ''));
            });
            $output->writeln(sprintf('  %d files: %d sorted, %d unknown, %d errors', $stats['files'], $stats['sorted'], $stats['unknown'], $stats['errors']));
        }

        return 0;
    }
}
