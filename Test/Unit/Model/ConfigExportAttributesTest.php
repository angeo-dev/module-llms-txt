<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model;

use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class ConfigExportAttributesTest extends TestCase
{
    public function testCostIsNeverExported(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => match ($path) {
                Config::XML_PATH_PD_ATTRIBUTES => "color, COST\nmaterial",
                default => '',
            }
        );

        $codes = (new Config($scopeConfig))->getExportAttributes($this->createMock(StoreInterface::class));

        self::assertSame(['color', 'material'], $codes);
    }
}
