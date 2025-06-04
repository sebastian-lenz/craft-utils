<?php

namespace lenz\craft\utils\foreignField;

use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use yii\base\Event;

/**
 * Class ForeignFieldQueryListener
 */
class ForeignFieldQueryListener
{
  /**
   * @var bool
   */
  static private $_isInitialized = false;


  /**
   * @return void
   */
  static public function init(): void {
    if (!self::$_isInitialized) {
      self::$_isInitialized = true;

      Event::on(
        ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE,
        self::onBeforeQueryPrepare(...)
      );
    }
  }


  // Private methods
  // ---------------

  /**
   * @param CancelableEvent $event
   * @return void
   */
  private static function onBeforeQueryPrepare(CancelableEvent $event): void {
    $query = $event->sender;
    if (!($query instanceof ElementQuery) || !$query->withCustomFields) {
      return;
    }

    $fieldAttributes = $query->getBehavior('customFields');
    $fieldLayouts = \Craft::$app
      ->getFields()
      ->getLayoutsByType($query->elementType);

    $fields = array_filter(
      array_unique(array_reduce(
        $fieldLayouts,
        fn($fields, $fieldLayout) => array_merge($fields, $fieldLayout->getCustomFields()),
        []
      )),
      fn($field) => $field instanceof ForeignField
    );

    foreach ($fields as $field) {
      $queryClass = $field::queryExtensionClass();
      $handle = $field->handle;
      $filter = $fieldAttributes->$handle ?? null;

      $queryClass::attachTo($query, $field, [
        'filters' => $queryClass::prepareQueryFilter($field, $filter),
      ]);
    }
  }
}
