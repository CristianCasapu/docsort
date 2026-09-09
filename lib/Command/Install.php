<?php

declare(strict_types=1);

namespace OCA\DocSort\Command;

use OCA\DocSort\Service\PythonEnv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** The same installation the administration page offers, for people who prefer a terminal. */
final class Install extends Command
{
    public function __construct(private PythonEnv $env)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('docsort:install')
            ->setDescription('Install the document reader (Python environment with RapidOCR and pypdfium2) into the data directory')
            ->addOption('remove', null, InputOption::VALUE_NONE, 'remove the environment instead');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ((bool) $input->getOption('remove')) {
            $this->env->remove();
            $output->writeln('Removed '.$this->env->dir());

            return 0;
        }
        $status = $this->env->status();
        if ($status['installed']) {
            $output->writeln('<info>The reader is already installed: '.$status['python'].'</info> (use --remove first for a fresh one)');

            return 0;
        }

        try {
            $this->env->install(function (string $line) use ($output): void {
                $output->writeln($line);
            });
        } catch (\RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return 1;
        }
        $output->writeln('<info>The document reader is installed: '.$this->env->python().'</info>');

        return 0;
    }
}
