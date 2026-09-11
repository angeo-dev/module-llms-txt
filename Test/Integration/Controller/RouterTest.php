<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Integration\Controller;

use Magento\TestFramework\TestCase\AbstractController;

/**
 * The router is the one part of this module that cannot be unit tested
 * meaningfully — it depends on Magento's front-controller dispatch, on store
 * resolution and on config read through the real scope config. These tests
 * cover the failure that is hardest to notice from the outside: a route that
 * stops matching after a refactor, so the storefront quietly serves a 404
 * where a file used to be.
 *
 * Requires the Magento integration test framework — see
 * Test/Integration/README.md. Not run by phpunit.xml or by CI.
 *
 * @magentoAppIsolation enabled
 */
class RouterTest extends AbstractController
{
    /**
     * @magentoConfigFixture current_store angeo_llms/general/enabled 1
     * @magentoConfigFixture current_store angeo_llms/formats/generate_llms_txt 1
     */
    public function testLlmsTxtRouteIsMatched(): void
    {
        $this->dispatch('/llms.txt');

        // Either the generated file (200) or the controller's own "not
        // generated yet" 404 — but never Magento's CMS no-route page, which
        // would mean the router never claimed the path.
        self::assertNotSame(
            'cms',
            $this->getRequest()->getModuleName(),
            '/llms.txt fell through to the CMS no-route handler — the router did not match it.'
        );
    }

    /**
     * @magentoConfigFixture current_store angeo_llms/general/enabled 1
     * @magentoConfigFixture current_store angeo_llms/formats/generate_agentic_sitemap 1
     */
    public function testAgenticSitemapRouteIsMatched(): void
    {
        $this->dispatch('/sitemap_agentic_discovery.xml');

        self::assertNotSame('cms', $this->getRequest()->getModuleName());
    }

    /**
     * @magentoConfigFixture current_store angeo_llms/general/enabled 0
     */
    public function testDisabledModuleDoesNotClaimTheRoute(): void
    {
        // With the module off, /llms.txt must behave like any unknown path so
        // a merchant can serve their own file from the web root instead.
        $this->dispatch('/llms.txt');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }
}
