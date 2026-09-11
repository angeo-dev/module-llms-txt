<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Angeo\LlmsTxt\Api\OutputContextInterface;

/**
 * Single source of truth for the on-disk layout of generated files (media-
 * relative). Both pipelines (legacy generators and the single-pass generator)
 * and the frontend controller resolve paths through this class, so the URLs
 * keep serving identical paths regardless of generation mode.
 *
 *   media/angeo/llms/llms_{storeCode}.txt
 *   media/angeo/llms/llms-full_{storeCode}.txt
 *   media/angeo/llms/llms_{storeCode}.jsonl
 *   media/angeo/llms/agents_{storeCode}.md
 *
 * @api
 * @since 3.2.0
 */
class FilePathResolver
{
    public const SUB_DIR = 'angeo/llms';

    private const LAYOUT = [
        OutputContextInterface::FORMAT_LLMS_TXT      => ['llms', 'txt'],
        OutputContextInterface::FORMAT_LLMS_FULL_TXT => ['llms-full', 'txt'],
        OutputContextInterface::FORMAT_JSONL         => ['llms', 'jsonl'],
        OutputContextInterface::FORMAT_AGENTS_MD     => ['agents', 'md'],
    ];

    /**
     * Media-relative path of the generated file for a format + store.
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
