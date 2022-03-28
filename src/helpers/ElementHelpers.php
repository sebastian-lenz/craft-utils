<?php

namespace lenz\craft\utils\helpers;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\ArrayHelper;
use Exception;
use Throwable;

/**
 * Class ElementHelpers
 */
class ElementHelpers
{
  /**
   * @param ElementInterface|null $element
   * @return array|null
   */
  static public function serialize(ElementInterface $element = null): ?array {
    if (is_null($element)) {
      return null;
    }

    $siteId = null;
    try {
      if (!empty($element->siteId)) {
        $siteId = intval($element->siteId);
      }
    } catch (Throwable) {
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
   * @throws Exception
   */
  static public function unserialize(mixed $value): ?ElementInterface {
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
