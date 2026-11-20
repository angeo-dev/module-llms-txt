<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Config\Backend;

use Angeo\LlmsTxt\Model\Config\Backend\CronExpression;
use PHPUnit\Framework\TestCase;

class CronExpressionTest extends TestCase
{
    /**
     * @dataProvider valid
     */
    public function testValid(string $expr): void
    {
        self::assertTrue(CronExpression::isValid($expr));
    }

    /**
     * @return array<array{string}>
     */
    public static function valid(): array
    {
        return [['0 2 * * *'], ['*/15 * * * *'], ['0 1,13 * * MON-FRI'], ['30  4 1 JAN *']];
    }

    /**
     * @dataProvider invalid
     */
    public function testInvalid(string $expr): void
    {
        self::assertFalse(CronExpression::isValid($expr));
    }

    /**
     * @return array<array{string}>
     */
    public static function invalid(): array
    {
        return [['0 2 * *'], ['0 2 * * * *'], ['every night'], ['0 2 * * ;rm'], ['']];
    }
}
