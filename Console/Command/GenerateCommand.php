<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Console\Command;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\JsonlGenerator;
use Angeo\LlmsTxt\Model\LlmsGenerator;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    private const OPT_STORE    = 'store';
    private const OPT_NO_JSONL = 'no-jsonl';
    private const OPT_NO_LLMS  = 'no-llms';

    public function __construct(
        private readonly LlmsGenerator         $llmsGenerator,
        private readonly JsonlGenerator        $jsonlGenerator,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config                $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:llms:generate')
            ->setDescription('Generate llms.txt and JSONL files for AI engine optimization.')
            ->addOption(self::OPT_STORE,    's', InputOption::VALUE_OPTIONAL, 'Store code to generate for (default: all active stores)')
            ->addOption(self::OPT_NO_JSONL, null, InputOption::VALUE_NONE,   'Skip JSONL generation')
            ->addOption(self::OPT_NO_LLMS,  null, InputOption::VALUE_NONE,   'Skip llms.txt generation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeCode = $input->getOption(self::OPT_STORE) ?: null;
        $skipJsonl = (bool) $input->getOption(self::OPT_NO_JSONL);
        $skipLlms  = (bool) $input->getOption(self::OPT_NO_LLMS);

        $output->writeln('');
        $output->writeln('<info>Angeo LLMs.txt Generator</info>');
        $output->writeln('');

        if (!$this->config->isEnabled()) {
            $output->writeln('<comment>Module is disabled in Stores → Configuration → Angeo → LLMs.txt</comment>');
            return Command::SUCCESS;
        }

        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        $active = array_filter(iterator_to_array($stores), fn($s) => $s->isActive());

        // Filter out stores excluded in config (unless a specific store was requested)
        if (!$storeCode) {
            $active = array_filter($active, function ($store) use ($output) {
                if ($this->config->isStoreExcluded($store)) {
                    $output->writeln(sprintf(
                        '  <comment>Skipping %s</comment> (excluded in Stores → Configuration → Angeo → LLMs.txt)',
                        $store->getCode()
                    ));
                    return false;
                }
                return true;
            });
        }

        if (empty($active)) {
            $output->writeln('<comment>No active stores found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            'Generating for <comment>%d</comment> store(s): <comment>%s</comment>',
            count($active),
            implode(', ', array_map(fn($s) => $s->getCode(), $active))
        ));
        $output->writeln('');

        $errors = 0;

        if (!$skipLlms) {
            $output->write('  Generating llms.txt... ');
            $start = microtime(true);
            try {
                $this->llmsGenerator->generate($storeCode);
                $output->writeln(sprintf('<info>done</info> (%.2fs)', microtime(true) - $start));
            } catch (\Throwable $e) {
                $output->writeln('<error>FAILED: ' . $e->getMessage() . '</error>');
                $errors++;
            }
        }

        if (!$skipJsonl) {
            $output->write('  Generating JSONL...   ');
            $start = microtime(true);
            try {
                $this->jsonlGenerator->generate($storeCode);
                $output->writeln(sprintf('<info>done</info> (%.2fs)', microtime(true) - $start));
            } catch (\Throwable $e) {
                $output->writeln('<error>FAILED: ' . $e->getMessage() . '</error>');
                $errors++;
            }
        }

        $output->writeln('');
        foreach ($active as $store) {
            $txtPath  = $this->llmsGenerator->getFilePath($store->getCode());
            $jsonlPath = $this->jsonlGenerator->getFilePath($store->getCode());
            $output->writeln(sprintf('  <comment>%s</comment>', $store->getCode()));
            if (!$skipLlms) {
                $size = file_exists($txtPath) ? $this->formatBytes(filesize($txtPath)) : 'not generated';
                $output->writeln("    llms.txt  → {$txtPath} ({$size})");
            }
            if (!$skipJsonl) {
                $size = file_exists($jsonlPath) ? $this->formatBytes(filesize($jsonlPath)) : 'not generated';
                $output->writeln("    llms.jsonl → {$jsonlPath} ({$size})");
            }
        }
        $output->writeln('');

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
