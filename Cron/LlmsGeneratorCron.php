<?php

/**
 * @copyright Copyright (c) 2026 Ievgenii Gryshkun
 * @author    Ievgenii Gryshkun <info@angeo.dev>
 * @license   MIT
 */

declare(strict_types=1);

namespace Angeo\LlmsTxt\LlmsTxt\LlmsTxt\Cron;

use Angeo\LlmsTxt\LlmsTxt\Model\JsonlGenerator;
use Angeo\LlmsTxt\LlmsTxt\Model\LlmsGenerator;

class LlmsGeneratorCron
{
    public function __construct(
        private readonly LlmsGenerator $llmsGenerator,
        private readonly JsonlGenerator $jsonlGenerator
    ) {}

    public function execute(): void
    {
        $this->llmsGenerator->generate();
        $this->jsonlGenerator->generate();
    }
}
