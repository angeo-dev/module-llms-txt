<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model;

use Angeo\LlmsTxt\Model\Generator\AbstractGenerator;

/**
 * Generates spec-compliant llms.txt (and llms-full.txt) files per store.
 *
 * Output format follows llmstxt.org specification:
 *   # Store Name                    ← required H1
 *   > Short store description       ← optional blockquote
 *
 *   ## Products                     ← H2 sections
 *   - [Product Name](url): desc
 *
 *   ## Categories
 *   - [Category](url): desc
 *
 * File location: media/angeo/llms/llms_{store_code}.txt
 */
class LlmsGenerator extends AbstractGenerator
{
    protected function getExtension(): string
    {
        return 'txt';
    }
}
