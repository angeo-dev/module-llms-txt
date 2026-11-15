<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Etc;

use PHPUnit\Framework\TestCase;

class ConfigXmlTest extends TestCase
{
    public function testCronJobIsOnlyDeclaredInDedicatedGroup(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 3) . '/etc/config.xml');
        $crontab = simplexml_load_file(dirname(__DIR__, 3) . '/etc/crontab.xml');

        self::assertCount(0, $config->xpath('/config/default/crontab'));
        self::assertCount(1, $crontab->xpath('/config/group[@id="angeo_llms"]/job[@name="angeo_llms_generate"]'));
    }
}
