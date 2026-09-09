<?php

declare(strict_types=1);

namespace OCA\DocSort\Command;

use OCA\DocSort\Service\Classifier;
use OCA\DocSort\Service\Extractor;
use OCA\DocSort\Service\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** What a file on the server would be sorted as — for trying the rules. Nothing is stored. */
final class Classify extends Command
{
    public function __construct(private Extractor $extractor, private Settings $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('docsort:classify')
            ->setDescription('Read a file on the server and say what kind of document it is (stores nothing)')
            ->addArgument('file', InputArgument::REQUIRED)
            ->addOption('text', 't', InputOption::VALUE_NONE, 'also print the text that was read');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('file');
        if (!is_file($path)) {
            $output->writeln('<error>not a file: '.$path.'</error>');

            return 1;
        }
        try {
            $read = $this->extractor->textOfPath($path);
        } catch (\RuntimeException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return 1;
        }
        $decision = Classifier::classify($read['text'], $this->settings->rules());
        $output->writeln(sprintf('read %d characters with %s', $read['chars'], $read['engine']));
        if ((bool) $input->getOption('text')) {
            $output->writeln('---');
            $output->writeln(mb_substr($read['text'], 0, 3000));
            $output->writeln('---');
        }
        $output->writeln(null === $decision['kind']
            ? sprintf('kind: unknown (best score %d, needs %d; words: %s)', $decision['score'], $decision['min'], implode(', ', $decision['hits']))
            : sprintf('kind: %s (%s), score %d (needs %d); words: %s%s', $decision['kind'], $decision['category'], $decision['score'], $decision['min'], implode(', ', $decision['hits']), null !== $decision['runnerUp'] ? '; runner-up '.$decision['runnerUp'].' ('.$decision['runnerUpScore'].')' : ''));

        return 0;
    }
}
