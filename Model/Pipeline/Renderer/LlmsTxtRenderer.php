<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Renderer;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\MarkdownUrl;
use Angeo\LlmsTxt\Model\Text\Truncator;

/**
 * Compact markdown renderer (llms.txt). Output mirrors the legacy
 * Llms\{Store,Category,CmsPage,Product}Provider compact branches:
 *
 *   # Store · > summary · meta line
 *   ## Categories: - [Name](url): desc≤200
 *   ## Pages:      - [Title](url): excerpt≤500
 *   ## Optional/### Products (or ## Products): - [Name](url): short≤500 — price CUR
 *
 * @since 3.2.0
 */
class LlmsTxtRenderer extends AbstractRenderer
{
    private const CATEGORY_DESC_MAX = 200;
    private const CMS_EXCERPT_MAX   = 500;
    private const PRODUCT_SHORT_MAX = 500;

    private ?string $openSection = null;

    public function __construct(
        Truncator $truncator,
        private readonly Config $config,
        ?MarkdownUrl $markdownUrl = null
    ) {
        parent::__construct($truncator, $config, $markdownUrl ?? new MarkdownUrl());
    }

    public function reset(): void
    {
        $this->openSection = null;
    }

    public function render(EntityRecordInterface $record, OutputContextInterface $context): iterable
    {
        switch ($record->getType()) {
            case EntityRecordInterface::TYPE_STORE:
                yield "# {$record->getName()}\n\n";
                // A store with no meta description would otherwise emit a
                // bare "> " — an empty blockquote, which is worse than none:
                // it looks like a summary to a parser and carries nothing.
                $summary = trim((string) $record->getSummary());
                if ($summary !== '') {
                    yield "> {$summary}\n\n";
                }
                yield sprintf(
                    "Base URL: %s · Currency: %s · Locale: %s\n\n",
                    $record->getUrl(),
                    $context->getCurrencyCode(),
                    $context->getLocaleCode()
                );
                return;

            case EntityRecordInterface::TYPE_CATEGORY:
                yield from $this->enterSection('categories', "## Categories\n\n");
                $line = sprintf(
                    '- [%s](%s)',
                    $this->escapeMarkdown($record->getName()),
                    $this->publicUrl($record->getUrl(), $context)
                );
                $desc = $this->truncator->truncate($record->getContent(), self::CATEGORY_DESC_MAX);
                if ($desc !== '') {
                    $line .= ': ' . $desc;
                }
                yield $line . "\n";
                return;

            case EntityRecordInterface::TYPE_CMS_PAGE:
                yield from $this->enterSection('pages', "## Pages\n\n");
                $line = sprintf(
                    '- [%s](%s)',
                    $this->escapeMarkdown($record->getName()),
                    $this->publicUrl($record->getUrl(), $context)
                );
                $excerpt = $this->truncator->truncate($record->getContent(), self::CMS_EXCERPT_MAX);
                if ($excerpt !== '') {
                    $line .= ': ' . $excerpt;
                }
                yield $line . "\n";
                return;

            case EntityRecordInterface::TYPE_PRODUCT:
                $header = $this->config->areProductsUnderOptional($context->getStore())
                    ? "## Optional\n\n### Products\n\n"
                    : "## Products\n\n";
                yield from $this->enterSection('products', $header);

                $line = sprintf(
                    '- [%s](%s)',
                    $this->escapeMarkdown($record->getName()),
                    $this->publicUrl($record->getUrl(), $context)
                );
                $parts = [];
                $short = $this->truncator->truncate($record->getShortContent(), self::PRODUCT_SHORT_MAX);
                if ($short !== '') {
                    $parts[] = $short;
                }
                $price = $record->getPrice();
                if ($price !== null && $price > 0.0) {
                    $parts[] = sprintf(
                        '%s %s',
                        number_format($price, 2, '.', ''),
                        $context->getCurrencyCode()
                    );
                }
                $inStock = $record->isInStock();
                if ($inStock !== null) {
                    $parts[] = $inStock ? 'In stock' : 'Out of stock';
                }
                if ($parts !== []) {
                    $line .= ': ' . implode(' — ', $parts);
                }
                yield $line . "\n";
                return;
        }
    }

    public function finish(OutputContextInterface $context): iterable
    {
        if ($this->openSection !== null) {
            yield "\n";
        }
    }

    /**
     * Close the previous section (legacy trailing "\n") and open a new header.
     *
     * @return iterable<string>
     */
    private function enterSection(string $section, string $header): iterable
    {
        if ($this->openSection === $section) {
            return;
        }
        if ($this->openSection !== null) {
            yield "\n";
        }
        $this->openSection = $section;
        yield $header;
    }
}
