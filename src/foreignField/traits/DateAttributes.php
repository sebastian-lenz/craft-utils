<?php

namespace lenz\craft\utils\foreignField\traits;

use craft\helpers\DateTimeHelper;

/**
 * Trait DateAttributes
 * Provides a similar functionality as Model::datetimeAttributes()
 * but for date only attributes.
 *
 * Make sure to call this trait within the init function of the model:
 *
 * ```
 *   public function init() {
 *     parent::init();
 *     $this->initDateAttributes();
 *   }
 * ```
 */
trait DateAttributes
{
  /**
   * @return array
   */
  abstract function dateAttributes();

  /**
   * @inheritDoc
   * @throws \Exception
   */
  protected function initDateAttributes() {
    foreach ($this->dateAttributes() as $attribute) {
      if ($this->$attribute !== null) {
        // Remove the timezone from form form submissions
        $value = $this->$attribute;
        if (is_array($value)) {
          unset($value['timezone']);
        }

        $this->$attribute = DateTimeHelper::toDateTime($value, false, false);
      }
    }
  }
}
