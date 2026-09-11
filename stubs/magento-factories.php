<?php
/**
 * PHPStan stubs for Magento factories.
 *
 * Magento generates *Factory classes at runtime, so they do not exist on disk
 * during static analysis. Declaring them here is deterministic across PHP
 * versions, unlike the on-the-fly factory autoloader in phpstan-magento.
 *
 * These declarations are never executed — PHPStan reads them for reflection only.
 *
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */

namespace Magento\Framework\Controller\Result {

    class RawFactory
    {
        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Raw
        {
        }
    }
}

namespace Magento\Catalog\Model\ResourceModel\Product {

    class CollectionFactory
    {
        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Collection
        {
        }
    }
}

namespace Magento\Catalog\Model\ResourceModel\Category {

    class CollectionFactory
    {
        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Collection
        {
        }
    }
}

namespace Magento\Cms\Model\ResourceModel\Page {

    class CollectionFactory
    {
        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Collection
        {
        }
    }
}

namespace Magento\Cron\Model\ResourceModel\Schedule {

    class CollectionFactory
    {
        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Collection
        {
        }
    }
}

namespace Magento\Cron\Model {

    class ScheduleFactory
    {
        /**
         * @param array<string, mixed> $data
         */
        public function create(array $data = []): Schedule
        {
        }
    }
}
