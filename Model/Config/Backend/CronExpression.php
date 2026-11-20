<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Rejects a malformed cron expression at save time.
 *
 * Before 5.0.0 a typo was saved as-is; Magento_Cron then failed to schedule
 * the job and generation silently stopped.
 *
 * @since 5.0.0
 */
class CronExpression extends Value
{
    /** One cron field: *, n, n-m, names (MON, JAN), lists and steps. */
    private const ITEM  = '(\*|[0-9A-Za-z]+(-[0-9A-Za-z]+)?)(\/\d+)?';
    private const FIELD = self::ITEM . '(,' . self::ITEM . ')*';

    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());
        if ($value === '') {
            $value = '0 2 * * *';
        }

        if (!self::isValid($value)) {
            throw new LocalizedException(
                __('"%1" is not a valid cron expression. Use five fields, for example: 0 2 * * *', $value)
            );
        }

        $this->setValue((string) preg_replace('/\s+/', ' ', $value));

        return parent::beforeSave();
    }

    public static function isValid(string $expression): bool
    {
        $fields = preg_split('/\s+/', trim($expression)) ?: [];
        if (count($fields) !== 5) {
            return false;
        }
        foreach ($fields as $field) {
            if (preg_match('#^' . self::FIELD . '$#', $field) !== 1) {
                return false;
            }
        }

        return true;
    }
}
