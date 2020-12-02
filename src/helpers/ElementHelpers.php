<?php

namespace lenz\craft\utils\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\ArrayHelper;

/**
 * Class ElementHelpers
 */
class ElementHelpers
{
  /**
   * @param ElementInterface|null $element
   * @return array|null
   */
  static public function serialize(ElementInterface $element = null) {
    if (is_null($element)) {
      return null;
    }

    $siteId = null;
    try {
      if (!empty($element->siteId)) {
        $siteId = intval($element->siteId);
      }
    } catch (\Throwable $error) {
      // Ignore
    }

    return [
      'id' => $element->getId(),
      'siteId' => $siteId,
      'type' => get_class($element),
    ];
  }

  /**
   * @param mixed $value
   * @return ElementInterface|null
   */
  static public function unserialize($value) {
    $id = ArrayHelper::getValue($value, 'id');
    if (is_null($id)) {
      return null;
    }

    return Craft::$app->getElements()->getElementById(
      $id,
      ArrayHelper::getValue($value, 'type'),
      ArrayHelper::getValue($value, 'siteId')
    );
  }
}
