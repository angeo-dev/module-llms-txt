<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Adminhtml\Generate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Cron\Model\ResourceModel\Schedule as ScheduleResource;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Cron\Model\Schedule as CronSchedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Admin "Generate Now (Async)" — schedules the next cron tick to run our generation
 * immediately, returning right away so the admin request doesn't block on a
 * minutes-long generation.
 *
 * Implementation: insert a pending row into cron_schedule with scheduled_at = now,
 * which the default cron group picks up within ~60 seconds.
 *
 * Hardening (3.1.0): repeated clicks no longer pile up duplicate pending jobs —
 * if a pending/running angeo_llms_generate row already exists, we reuse it and
 * tell the admin instead of inserting another.
 *
 * @since 3.0.0
 */
class Schedule extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_LlmsTxt::generate';

    private const JOB_CODE = 'angeo_llms_generate';

    private readonly ScheduleResource $scheduleResource;

    public function __construct(
        Context $context,
        private readonly ScheduleFactory $scheduleFactory,
        private readonly ScheduleCollectionFactory $scheduleCollectionFactory,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
        ?ScheduleResource $scheduleResource = null
    ) {
        parent::__construct($context);
        // Optional with a fallback so the constructor stays compatible with 4.3.x.
        $this->scheduleResource = $scheduleResource
            ?? ObjectManager::getInstance()->get(ScheduleResource::class);
    }

    public function execute()
    {
        try {
            if ($this->hasActiveJob()) {
                $this->messageManager->addNoticeMessage(
                    __('A generation run is already queued or in progress — no new run was scheduled.')
                );
                return $this->redirectBack();
            }

            $now = $this->dateTime->gmtTimestamp();
            $schedule = $this->scheduleFactory->create();
            $schedule
                ->setJobCode(self::JOB_CODE)
                ->setStatus(CronSchedule::STATUS_PENDING)
                ->setCreatedAt(date('Y-m-d H:i:s', $now))
                ->setScheduledAt(date('Y-m-d H:i:s', $now));
            // Magento_Cron has no repository for Schedule; the resource model
            // is the supported way to persist it.
            $this->scheduleResource->save($schedule);

            $this->messageManager->addSuccessMessage(
                __('Generation queued. The cron will pick it up within ~60 seconds. Check status below to follow progress.')
            );
        } catch (\Throwable $e) {
            // Do not surface raw exception internals in the UI; log them instead.
            $this->logger->error(
                '[Angeo LlmsTxt] Could not schedule generation: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('Could not schedule generation. See var/log/system.log for details.')
            );
        }

        return $this->redirectBack();
    }

    private function hasActiveJob(): bool
    {
        $collection = $this->scheduleCollectionFactory->create();
        $collection
            ->addFieldToFilter('job_code', self::JOB_CODE)
            ->addFieldToFilter('status', ['in' => [
                CronSchedule::STATUS_PENDING,
                CronSchedule::STATUS_RUNNING,
            ]])
            ->setPageSize(1);

        return $collection->getSize() > 0;
    }

    private function redirectBack()
    {
        return $this->resultRedirectFactory->create()->setPath(
            'adminhtml/system_config/edit/section/angeo_llms'
        );
    }
}
