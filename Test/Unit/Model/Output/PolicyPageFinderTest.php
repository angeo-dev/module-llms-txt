<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Output;

use Angeo\LlmsTxt\Model\Output\PolicyPageFinder;
use PHPUnit\Framework\TestCase;

/**
 * 5.2.0: delivery / returns / privacy pages found by CMS identifier.
 */
class PolicyPageFinderTest extends TestCase
{
    public function testLumaDefaultsAndCommonNames(): void
    {
        self::assertSame([
            'Delivery' => 'shipping-policy',
            'Returns'  => 'returns',
            'Privacy'  => 'privacy-policy',
        ], PolicyPageFinder::match([
            'home', 'no-route', 'enable-cookies', 'about-us', 'customer-service',
            'privacy-policy-cookie-restriction-mode', 'privacy-policy', 'shipping-policy', 'returns',
        ]));
    }

    public function testOtherLanguages(): void
    {
        self::assertSame(
            ['Delivery' => 'verzending', 'Returns' => 'retourneren-en-ruilen', 'Privacy' => 'datenschutz'],
            PolicyPageFinder::match(['verzending', 'retourneren-en-ruilen', 'datenschutz'])
        );
    }

    public function testNoFalseFriends(): void
    {
        self::assertSame([], PolicyPageFinder::match(['home', 'shippingcontainer-sale', 'returning-customers-offer', 'about']));
    }
}
