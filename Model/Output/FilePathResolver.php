<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Single source of truth for the on-disk layout of generated files. The
 * generator, the frontend controller and the CLI resolve paths through this
 * class.
 *
 *   var/angeo/llms/llms_{storeCode}.txt
 *   var/angeo/llms/llms-full_{storeCode}.txt
 *   var/angeo/llms/llms_{storeCode}.jsonl
 *   var/angeo/llms/agents_{storeCode}.md
 *
 * 5.0.0: output moved from pub/media to var. Files under pub/media are served
 * by the web server directly, which bypassed every check the controller makes
 * (module enabled, store excluded/inactive, website restrictions) and kept
 * serving files after the module was switched off. var/ is never web-served,
 * so the controller is now the only way to read them.
 *
 * @api
 * @since 3.2.0
 */
class FilePathResolver
{
    public const SUB_DIR = 'angeo/llms';

    /**
     * Magento directory code the files live in.
     *
     * @since 5.0.0
     */
    public const DIRECTORY = DirectoryList::VAR_DIR;

    /**
     * Where releases before 5.0.0 wrote them. Only used to delete leftovers.
     *
     * @since 5.0.0
     */
    public const LEGACY_DIRECTORY = DirectoryList::MEDIA;

    private const LAYOUT = [
        OutputContextInterface::FORMAT_LLMS_TXT      => ['llms', 'txt'],
        OutputContextInterface::FORMAT_LLMS_FULL_TXT => ['llms-full', 'txt'],
        OutputContextInterface::FORMAT_JSONL         => ['llms', 'jsonl'],
        OutputContextInterface::FORMAT_AGENTS_MD     => ['agents', 'md'],
    ];

    /**
     * Path relative to {@see self::DIRECTORY} of the generated file for a format + store.
     */
    public function getRelativePath(string $format, string $storeCode): string
    {
        [$base, $ext] = self::LAYOUT[$format]
            ?? throw new \InvalidArgumentException("Unknown format: {$format}");

        return sprintf('%s/%s_%s.%s', self::SUB_DIR, $base, $storeCode, $ext);
    }

    /**
     * @return string[]  All known format codes.
     */
    public function getFormats(): array
    {
        return array_keys(self::LAYOUT);
    }
}
