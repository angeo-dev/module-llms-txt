<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Console\Command;

use Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey;
use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * `bin/magento angeo:llms:clean [--store=...] [--force]`
 *
 * Deletes the generated files and empties the markdown mirror cache.
 *
 * The reason this exists: after switching a format off, or excluding a store,
 * the stale file keeps being served until the next run happens to clean it up.
 * The same command is the honest way to remove the module's output before
 * uninstalling.
 *
 * @since 4.1.0
 */
class CleanCommand extends Command
{
    private const OPT_STORE = 'store';
    private const OPT_FORCE = 'force';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly FilePathResolver $pathResolver,
        private readonly Filesystem $filesystem,
        private readonly CacheInterface $cache
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:llms:clean')
            ->setDescription('Delete generated llms.txt / JSONL / agents.md files and flush the .md mirror cache.')
            ->addOption(self::OPT_STORE, 's', InputOption::VALUE_OPTIONAL, 'Store code (default: all)')
            ->addOption(self::OPT_FORCE, 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeCode = $input->getOption(self::OPT_STORE) ?: null;

        if (!$input->getOption(self::OPT_FORCE)) {
            $scope = $storeCode !== null ? sprintf('store "%s"', $storeCode) : 'ALL stores';
            $question = new ConfirmationQuestion(
                sprintf('Delete all generated Angeo LLMs.txt files for %s? [y/N] ', $scope),
                false
            );
            if (!$this->getHelper('question')->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');
                return Command::SUCCESS;
            }
        }

        $stores = $storeCode !== null
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $deleted = 0;

        foreach ($stores as $store) {
            foreach ($this->pathResolver->getFormats() as $format) {
                $path = $this->pathResolver->getRelativePath($format, $store->getCode());
                if (!$directory->isExist($path)) {
                    continue;
                }
                $directory->delete($path);
                $deleted++;
                $output->writeln(sprintf('  <info>removed</info> %s', $path));
            }
        }

        $this->cache->clean([MdMirrorCacheKey::TAG]);

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>%d file(s) removed; markdown mirror cache flushed.</info>',
            $deleted
        ));

        return Command::SUCCESS;
    }
}
