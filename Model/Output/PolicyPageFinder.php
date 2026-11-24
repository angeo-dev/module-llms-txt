<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Finds the delivery, returns and privacy CMS pages when the merchant did
 * not set them under agents.md → Key Pages (5.2.0).
 *
 * An agent about to recommend a product needs these terms, and most stores
 * have the pages but never fill the setting. Only active pages assigned to
 * the store view (or all views) count; the identifier decides, in English,
 * Dutch, German, French, Spanish and Ukrainian transliteration. The setting
 * always wins.
 *
 * @since 5.2.0
 */
class PolicyPageFinder
{
    /** label => identifier pattern; order = preference when several match */
    public const PATTERNS = [
        'Delivery' => '/(^|[-_])(shipping|delivery|verzending|bezorging|versand|lieferung|livraison|envio|envios|dostavka)([-_]|$)/i',
        'Returns'  => '/(^|[-_])(returns?|refunds?|retour(en|s)?|ruilen|widerruf|ruecksendung|rucksendung|retours|devoluciones|devolucion|povernennya)([-_]|$)/i',
        'Privacy'  => '/(^|[-_])(privacy|privacybeleid|datenschutz|confidentialite|privacidad|gdpr|avg)([-_]|$)/i',
    ];
    private const MAX_PAGES = 500;

    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    /**
     * @param list<string> $skip labels the merchant already configured
     * @return array<string,string> label => CMS identifier
     */
    public function find(StoreInterface $store, array $skip = []): array
    {
        if (array_diff(array_keys(self::PATTERNS), $skip) === []) {
            return [];
        }
        try {
            $collection = $this->collectionFactory->create();
            $collection->addStoreFilter((int) $store->getId());
            $collection->addFieldToFilter('is_active', 1);
            $collection->setPageSize(self::MAX_PAGES);
            $identifiers = [];
            foreach ($collection as $page) {
                $identifiers[] = (string) $page->getIdentifier();
            }
        } catch (\Throwable) {
            return [];
        }

        return array_diff_key(self::match($identifiers), array_flip($skip));
    }

    /**
     * Pure part, unit tested. Shortest match wins ("privacy-policy" before
     * "privacy-policy-cookie-restriction-mode").
     *
     * @param list<string> $identifiers
     * @return array<string,string>
     */
    public static function match(array $identifiers): array
    {
        usort($identifiers, static fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        $out = [];
        foreach (self::PATTERNS as $label => $pattern) {
            foreach ($identifiers as $id) {
                if ($id !== '' && preg_match($pattern, $id) === 1) {
                    $out[$label] = $id;
                    break;
                }
            }
        }

        return $out;
    }
}
