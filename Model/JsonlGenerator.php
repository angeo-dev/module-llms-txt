<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model;

use Angeo\LlmsTxt\Model\Generator\AbstractGenerator;

/**
 * Generates JSONL files for vector indexing / embedding pipelines.
 *
 * Each line is a valid JSON object with an embedding_text field.
 * File location: media/angeo/llms/llms_{store_code}.jsonl
 */
class JsonlGenerator extends AbstractGenerator
{
    protected function getExtension(): string
    {
        return 'jsonl';
    }
}
