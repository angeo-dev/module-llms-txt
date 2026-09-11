<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Console\Command;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Generator\GenerationSummary;
use Angeo\LlmsTxt\Service\GenerationService;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento angeo:llms:generate [--store=...] [--no-jsonl] [--no-llms] [--no-full]`
 *
 * @since 3.0.0
 */
class GenerateCommand extends Command
{
    private const OPT_STORE   = 'store';
    private const OPT_NO_JSONL = 'no-jsonl';
    private const OPT_NO_LLMS  = 'no-llms';
    private const OPT_NO_FULL  = 'no-full';

    public function __construct(
        private readonly GenerationService $generationService,
        private readonly Config $config,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:llms:generate')
            ->setDescription('Generate llms.txt, llms-full.txt, and JSONL files.')
            ->addOption(self::OPT_STORE,    's', InputOption::VALUE_OPTIONAL, 'Store code (default: all eligible stores)')
            ->addOption(self::OPT_NO_JSONL, null, InputOption::VALUE_NONE,    'Skip JSONL generation')
            ->addOption(self::OPT_NO_LLMS,  null, InputOption::VALUE_NONE,    'Skip llms.txt generation')
            ->addOption(self::OPT_NO_FULL,  null, InputOption::VALUE_NONE,    'Skip llms-full.txt generation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Set area code if not already set (so emulation works).
        try {
            $this->appState->getAreaCode();
        } catch (\Throwable) {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        }

        $output->writeln('');
        $output->writeln('<info>Angeo LLMs.txt Generator ' . Config::MODULE_VERSION . '</info>');
        $output->writeln('');

        if (!$this->config->isEnabled()) {
            $output->writeln('<comment>Module is disabled in Stores → Configuration → Angeo → LLMs.txt</comment>');
            return Command::SUCCESS;
        }

        $storeCode = $input->getOption(self::OPT_STORE) ?: null;
        $skip = [
            'llms_txt'      => (bool) $input->getOption(self::OPT_NO_LLMS),
            'llms_full_txt' => (bool) $input->getOption(self::OPT_NO_FULL),
            'jsonl'         => (bool) $input->getOption(self::OPT_NO_JSONL),
        ];

        $start = microtime(true);
        try {
            $summaries = $this->generationService->generateAll($storeCode, $skip);
        } catch (\Throwable $e) {
            $output->writeln('<error>FAILED: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $this->renderSummaries($output, $summaries);

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Total time: %.2fs</info>',
            microtime(true) - $start
        ));

        $anySuccess = false;
        foreach ($summaries as $summary) {
            if ($summary->getSuccesses() !== []) {
                $anySuccess = true;
                break;
            }
        }
        if ($anySuccess) {
            $output->writeln('');
            $output->writeln(
                '<comment>Next: check how AI engines actually see this store — '
                . 'free AEO scan at https://angeo.dev/scan?utm_source=cli</comment>'
            );
        }

        foreach ($summaries as $summary) {
            if ($summary->hasFailures()) {
                return Command::FAILURE;
            }
        }
        return Command::SUCCESS;
    }

    /**
     * @param array<string, GenerationSummary> $summaries
     */
    private function renderSummaries(OutputInterface $output, array $summaries): void
    {
        foreach ($summaries as $format => $summary) {
            $output->writeln('');
            $output->writeln(sprintf('<comment>%s</comment>', $format));

            foreach ($summary->getSuccesses() as $storeCode => $data) {
                $output->writeln(sprintf(
                    '  <info>✓</info> %s — %s, %d items, %.2fs',
                    $storeCode,
                    $this->formatBytes($data['bytes']),
                    $data['items'],
                    $data['duration']
                ));
            }
            foreach ($summary->getFailures() as $storeCode => $error) {
                $output->writeln(sprintf('  <error>✗ %s — %s</error>', $storeCode, $error));
            }
            foreach ($summary->getSkipped() as $storeCode) {
                $output->writeln(sprintf('  <comment>– %s (skipped)</comment>', $storeCode));
            }
        }
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
