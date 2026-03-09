<?php

/**
 * @copyright Copyright (c) 2026 Ievgenii Gryshkun
 * @author    Ievgenii Gryshkun <info@angeo.dev>
 * @license   MIT
 */

declare(strict_types=1);

namespace Angeo\LlmsTxt\Cron;

use Angeo\Model\JsonlGenerator;
use Angeo\Model\LlmsGenerator;

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
