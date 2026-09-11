<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Console\Command;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento angeo:llms:validate [--store=...] [--strict]`
 *
 * Lints the generated files against the llms.txt specification, v2
 * (llmstxt.org, 10 August 2026):
 *
 *   - the file starts with an H1 — the only section the spec requires;
 *   - an optional blockquote summary directly under it, at most one;
 *   - free markdown between the blockquote and the first H2, but NO headings
 *     there (v2 wording: "sections of any type except headings");
 *   - after that, only H2 sections whose list items are markdown links,
 *     optionally followed by ": notes";
 *   - links are absolute;
 *   - JSONL: exactly one valid JSON object per line.
 *
 * Findings are errors (spec violations) or warnings (things that will not
 * break a parser but weaken the file). `--strict` promotes warnings to errors,
 * which is what you want in CI.
 *
 * All reads go through Magento's Filesystem abstraction, so this works on
 * Adobe Commerce Cloud and any remote-storage setup.
 *
 * @since 3.0.0
 */
class ValidateCommand extends Command
{
    private int $errors = 0;
    private int $warnings = 0;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly FilePathResolver $pathResolver,
        private readonly Filesystem $filesystem,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:llms:validate')
            ->setDescription('Validate generated llms.txt + JSONL files against the llms.txt v2 spec.')
            ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store code (default: all)')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Treat warnings as errors');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeCode = $input->getOption('store');
        $strict    = (bool) $input->getOption('strict');

        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : array_filter(
                $this->storeManager->getStores(),
                static fn($s) => !method_exists($s, 'isActive') || $s->isActive()
            );

        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

        foreach ($stores as $store) {
            $this->validateStore($store, $directory, $output);
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<comment>%d error(s), %d warning(s).</comment>',
            $this->errors,
            $this->warnings
        ));

        if ($this->errors > 0 || ($strict && $this->warnings > 0)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function validateStore(
        StoreInterface $store,
        ReadInterface $directory,
        OutputInterface $output
    ): void {
        $code = $store->getCode();
        $output->writeln('');
        $output->writeln(sprintf('<comment>Store: %s</comment>', $code));

        foreach (
            [
                OutputContextInterface::FORMAT_LLMS_TXT      => 'llms.txt',
                OutputContextInterface::FORMAT_LLMS_FULL_TXT => 'llms-full.txt',
            ] as $format => $label
        ) {
            $path = $this->pathResolver->getRelativePath($format, $code);
            if (!$directory->isFile($path)) {
                $output->writeln(sprintf('  <comment>· %s not generated</comment>', $label));
                continue;
            }
            $this->validateMarkdown((string) $directory->readFile($path), $label, $store, $output);
        }

        $jsonlPath = $this->pathResolver->getRelativePath(OutputContextInterface::FORMAT_JSONL, $code);
        if ($directory->isFile($jsonlPath)) {
            $this->validateJsonl((string) $directory->readFile($jsonlPath), $output);
        } else {
            $output->writeln('  <comment>· llms.jsonl not generated</comment>');
        }
    }

    private function validateMarkdown(
        string $content,
        string $label,
        StoreInterface $store,
        OutputInterface $output
    ): void {
        $lines = explode("\n", $content);

        // ── H1: the only required section ────────────────────────────────
        $firstIndex = null;
        foreach ($lines as $i => $line) {
            if (trim($line) !== '') {
                $firstIndex = $i;
                break;
            }
        }
        if ($firstIndex === null) {
            $this->error($output, sprintf('%s: file is empty', $label));
            return;
        }

        $first = ltrim($lines[$firstIndex], "\xEF\xBB\xBF"); // an optional BOM is allowed
        if (!str_starts_with($first, '# ')) {
            $this->error($output, sprintf('%s: does not start with an H1', $label));
        } else {
            $this->ok($output, sprintf('%s: H1 present', $label));
        }

        // ── Preamble: blockquote count + no headings before the first H2 ──
        $blockquotes = 0;
        $strayHeading = null;
        for ($i = $firstIndex + 1, $n = count($lines); $i < $n; $i++) {
            $line = $lines[$i];
            if (str_starts_with($line, '## ')) {
                break;
            }
            if (str_starts_with($line, '>')) {
                if (trim(ltrim($line, '>')) === '') {
                    $this->error($output, sprintf(
                        '%s: empty blockquote — a bare "> " reads as a summary and says nothing. '
                        . 'Set a meta description on the store, or a Custom Summary in the module config.',
                        $label
                    ));
                    continue;
                }
                $blockquotes++;
                continue;
            }
            if (preg_match('/^#{1,6} /', $line) === 1 && $strayHeading === null) {
                $strayHeading = $i + 1;
            }
        }

        if ($blockquotes === 0) {
            $this->warn($output, sprintf('%s: no blockquote summary (recommended by the spec)', $label));
        } elseif ($blockquotes > 1) {
            $this->warn($output, sprintf('%s: %d blockquote lines — the spec describes one summary', $label, $blockquotes));
        } else {
            $this->ok($output, sprintf('%s: single blockquote summary', $label));
        }

        if ($strayHeading !== null) {
            $this->error($output, sprintf(
                '%s: heading on line %d sits between the summary and the first H2; the spec allows any markdown there EXCEPT headings',
                $label,
                $strayHeading
            ));
        }

        // ── Section list items must be markdown links ─────────────────────
        $badItems = 0;
        $relative = 0;
        $htmlLinks = 0;
        $inSection = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, '## ')) {
                $inSection = true;
                continue;
            }
            if (!$inSection || !str_starts_with($line, '- ')) {
                continue;
            }
            if (preg_match('/^- \[[^]]*]\((\S+?)\)(:.*)?$/', $line, $m) !== 1) {
                $badItems++;
                continue;
            }
            $url = $m[1];
            if (preg_match('~^https?://~i', $url) !== 1) {
                $relative++;
            } elseif (!str_ends_with($url, '.md')) {
                $htmlLinks++;
            }
        }

        if ($badItems > 0) {
            $this->error($output, sprintf(
                '%s: %d list item(s) are not "- [name](url)" links',
                $label,
                $badItems
            ));
        } else {
            $this->ok($output, sprintf('%s: all section items are markdown links', $label));
        }

        if ($relative > 0) {
            $this->error($output, sprintf('%s: %d relative link(s); links must be absolute', $label, $relative));
        }

        if ($htmlLinks > 0 && $this->config->isMdMirrorEnabled($store)) {
            $this->warn($output, sprintf(
                '%s: %d link(s) point at HTML pages while markdown mirrors are served. '
                . 'llms.txt v2 asks that links point at LLM-friendly content — '
                . 'enable Formats → Link to Markdown Mirrors in llms.txt.',
                $label,
                $htmlLinks
            ));
        }
    }

    private function validateJsonl(string $content, OutputInterface $output): void
    {
        $bad = 0;
        $total = 0;

        foreach (explode("\n", $content) as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }
            $total++;
            $decoded = json_decode($line, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                $bad++;
            }
        }

        if ($bad > 0) {
            $this->error($output, sprintf('llms.jsonl: %d/%d lines are not a JSON object', $bad, $total));
            return;
        }

        $this->ok($output, sprintf('llms.jsonl: %d valid records', $total));
    }

    private function ok(OutputInterface $output, string $message): void
    {
        $output->writeln('  <info>✓ ' . $message . '</info>');
    }

    private function warn(OutputInterface $output, string $message): void
    {
        $this->warnings++;
        $output->writeln('  <comment>! ' . $message . '</comment>');
    }

    private function error(OutputInterface $output, string $message): void
    {
        $this->errors++;
        $output->writeln('  <error>✗ ' . $message . '</error>');
    }
}
