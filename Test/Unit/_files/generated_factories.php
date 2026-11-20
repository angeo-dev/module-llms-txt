<?php
/**
 * Stand-ins for Magento-generated factories, for unit tests run outside a
 * compiled Magento instance (module-only CI). Each is declared only when the
 * real generated class is not available.
 *
 * @license MIT
 */
declare(strict_types=1);

namespace Magento\Framework\Controller\Result;

if (!class_exists(RawFactory::class, false) && !class_exists(RawFactory::class)) {
    // phpcs:ignore
    class RawFactory
    {
        /**
         * @param array<string, mixed> $data
         * @return Raw
         */
        public function create(array $data = [])
        {
            throw new \LogicException('stub');
        }
    }
}
