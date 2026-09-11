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
 * Verbose markdown renderer (llms-full.txt). Output mirrors the legacy
 * full-txt branches: every entity gets a `### Name` block with URL and full
 * sanitized content; products never go under `## Optional`.
 *
 * @since 3.2.0
 */
class LlmsFullTxtRenderer extends AbstractRenderer
{
    private ?string $openSection = null;

    public function __construct(
        Truncator $truncator,
        ?Config $config = null,
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
                yield sprintf("### %s\n\n", $this->escapeMarkdown($record->getName()));
                yield $this->publicUrl($record->getUrl(), $context) . "\n\n";
                if ($record->getContent() !== '') {
                    yield $record->getContent() . "\n\n";
                }
                return;

            case EntityRecordInterface::TYPE_CMS_PAGE:
                yield from $this->enterSection('pages', "## Pages\n\n");
                yield sprintf("### %s\n\n", $this->escapeMarkdown($record->getName()));
                yield $this->publicUrl($record->getUrl(), $context) . "\n\n";
                if ($record->getContent() !== '') {
                    yield $record->getContent() . "\n\n";
                }
                return;

            case EntityRecordInterface::TYPE_PRODUCT:
                yield from $this->enterSection('products', "## Products\n\n");

                $out = sprintf(
                    "### %s\n\n%s\n\n",
                    $this->escapeMarkdown($record->getName()),
                    $this->publicUrl($record->getUrl(), $context)
                );
                $price = $record->getPrice();
                if ($price !== null && $price > 0.0) {
                    $out .= sprintf(
                        "Price: %s %s\n\n",
                        number_format($price, 2, '.', ''),
                        $context->getCurrencyCode()
                    );
                }
                $inStock = $record->isInStock();
                if ($inStock !== null) {
                    $out .= sprintf("Availability: %s\n\n", $inStock ? 'In stock' : 'Out of stock');
                }
                if ($record->getSku() !== null && $record->getSku() !== '') {
                    $out .= sprintf("SKU: %s\n\n", $record->getSku());
                }
                foreach ($record->getAttributes() as $code => $value) {
                    $out .= sprintf("%s: %s\n\n", ucfirst(str_replace('_', ' ', (string) $code)), $value);
                }
                if ($record->getImageUrl() !== null) {
                    $out .= sprintf("Image: %s\n\n", $record->getImageUrl());
                }
                $short = $record->getShortContent();
                $desc  = $record->getContent();
                if ($short !== '') {
                    $out .= $short . "\n\n";
                }
                if ($desc !== '' && $desc !== $short) {
                    $out .= $desc . "\n\n";
                }
                yield $out;
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
