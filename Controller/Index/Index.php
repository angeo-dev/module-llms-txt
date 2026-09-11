<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Controller\Router;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Serves the four AI-discovery files at the storefront, with proper MIME types,
 * Cache-Control + ETag + Last-Modified, Content-Length, and 404 short-circuits.
 *
 * Hardening (3.1.0):
 *  - All filesystem access goes through Magento's Filesystem abstraction
 *    (Adobe Commerce Cloud / remote-storage compatible — no native is_file/
 *    filemtime/file_get_contents on raw paths).
 *  - Large files are streamed to the client in fixed-size chunks instead of
 *    being loaded into PHP memory in one piece, so concurrent requests for a
 *    multi-hundred-MB llms-full.txt cannot exhaust the PHP memory limit.
 *
 * @since 3.0.0
 */
class Index implements ActionInterface, HttpGetActionInterface
{
    private const MIME = [
        'llms.txt'        => 'text/plain; charset=utf-8',
        'llms-full.txt'   => 'text/plain; charset=utf-8',
        'llms.jsonl'      => 'application/x-ndjson; charset=utf-8',
        // Alias: llms-full.jsonl serves the same dataset file as llms.jsonl.
        'llms-full.jsonl' => 'application/x-ndjson; charset=utf-8',
        'agents.md'       => 'text/markdown; charset=utf-8',
    ];

    /** Files at or below this size are returned via a Raw result (FPC-friendly). */
    private const INLINE_MAX_BYTES = 4194304; // 4 MB

    /** Chunk size for the streaming branch. */
    private const STREAM_CHUNK_BYTES = 262144; // 256 KB

    public function __construct(
        private readonly HttpResponse $response,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly FilePathResolver $pathResolver,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        try {
            $store = $this->storeManager->getStore();
            $file  = (string) $this->request->getParam(Router::PARAM_FILE, 'llms.txt');

            if (!$this->config->isEnabled($store) || $this->config->isStoreExcluded($store)) {
                return $this->notFound("Not available.\n");
            }
            if (method_exists($store, 'isActive') && !$store->isActive()) {
                return $this->notFound("Not available.\n");
            }
            if (!isset(self::MIME[$file])) {
                return $this->notFound("Not found.\n");
            }

            $relativePath = $this->resolveRelativePath($file, $store->getCode());
            $directory    = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if ($relativePath === null || !$directory->isFile($relativePath)) {
                return $this->notFound("Not generated yet.\n");
            }

            $stat  = $directory->stat($relativePath);
            $mtime = (int) ($stat['mtime'] ?? 0);
            $size  = (int) ($stat['size'] ?? 0);
            $etag  = sprintf('"%s-%x-%x"', substr(hash('sha256', $relativePath), 0, 8), $mtime, $size);

            $ifNoneMatch = $this->request->getHeader('If-None-Match');
            $ifModifiedSince = $this->request->getHeader('If-Modified-Since');
            if (
                ($ifNoneMatch && trim((string) $ifNoneMatch) === $etag)
                || ($ifModifiedSince && strtotime((string) $ifModifiedSince) >= $mtime)
            ) {
                $this->response->setHttpResponseCode(304);
                $this->response->setHeader('ETag', $etag, true);
                $this->response->setHeader('Cache-Control', sprintf(
                    'public, max-age=%d',
                    $this->config->getHttpCacheTtl()
                ), true);
                return $this->response;
            }

            // Small files: Raw result (plays nicely with response plugins / FPC).
            // Large files: chunked streaming straight to the client.
            if ($size <= self::INLINE_MAX_BYTES) {
                $result = $this->resultRawFactory->create();
                $this->applyHeaders($result, $file, $etag, $mtime, $size);
                $result->setContents((string) $directory->readFile($relativePath));
                return $result;
            }

            return $this->streamFile($directory, $relativePath, $file, $etag, $mtime, $size);
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] Controller error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->response->setHttpResponseCode(500);
            $this->response->setBody("Internal error.\n");
            return $this->response;
        }
    }

    /**
     * Map URL → on-disk generated file path (relative to the media directory).
     * Resolved via FilePathResolver: identical paths regardless of which
     * generation pipeline (legacy or single-pass) produced the file.
     */
    private function resolveRelativePath(string $file, string $storeCode): ?string
    {
        $format = match ($file) {
            'llms.txt'        => OutputContextInterface::FORMAT_LLMS_TXT,
            'llms-full.txt'   => OutputContextInterface::FORMAT_LLMS_FULL_TXT,
            'llms.jsonl', 'llms-full.jsonl' => OutputContextInterface::FORMAT_JSONL,
            'agents.md'       => OutputContextInterface::FORMAT_AGENTS_MD,
            default => null,
        };
        return $format === null ? null : $this->pathResolver->getRelativePath($format, $storeCode);
    }

    /**
     * Apply the standard header set to either result type.
     */
    private function applyHeaders(
        Raw|HttpResponse $target,
        string $file,
        string $etag,
        int $mtime,
        int $size
    ): void {
        $target->setHeader('Content-Type', self::MIME[$file], true);
        $target->setHeader('Content-Length', (string) $size, true);
        $target->setHeader('Cache-Control', sprintf(
            'public, max-age=%d',
            $this->config->getHttpCacheTtl()
        ), true);
        $target->setHeader('ETag', $etag, true);
        $target->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT', true);
        $target->setHeader('X-Content-Type-Options', 'nosniff', true);
        $target->setHeader('X-Robots-Tag', 'noindex, follow', true);
    }

    /**
     * Stream the file body in fixed-size chunks — constant memory regardless of
     * file size. Headers are flushed first; the (already-empty) response object
     * is returned so the framework's send cycle is a no-op.
     */
    private function streamFile(
        ReadInterface $directory,
        string $relativePath,
        string $file,
        string $etag,
        int $mtime,
        int $size
    ): HttpResponse {
        $this->response->setHttpResponseCode(200);
        $this->applyHeaders($this->response, $file, $etag, $mtime, $size);
        $this->response->sendHeaders();

        $stream = $directory->openFile($relativePath);
        try {
            while (!$stream->eof()) {
                // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
                echo $stream->read(self::STREAM_CHUNK_BYTES);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        } finally {
            $stream->close();
        }

        return $this->response;
    }

    private function notFound(string $body): HttpResponse
    {
        $this->response->setHttpResponseCode(404);
        $this->response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $this->response->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->response->setBody($body);
        return $this->response;
    }
}
