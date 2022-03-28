<?php

namespace lenz\craft\utils\events;

use craft\base\ElementInterface;
use yii\base\Event;

/**
 * Class AnchorEvent
 */
class AnchorEvent extends AbstractElementEvent
{
  /**
   * @var string|null
   */
  public ?string $anchor = null;

  /**
   * @var string
   */
  public string $id;

  /**
   * @var string
   */
  const EVENT_RESOLVE_ANCHOR_ID = 'resolveAnchorId';

  /**
   * @var string
   */
  const ID_PREFIX = '#@id:';


  // Static methods
  // --------------

  /**
   * @param string $value
   * @return bool
   */
  static public function isAnchorId(string $value): bool {
    return str_starts_with($value, self::ID_PREFIX);
  }

  /**
   * @param string $anchor
   * @param ElementInterface $element
   * @return string|null
   * @noinspection PhpUnused (API method)
   */
  static public function resolveAnchor(string $anchor, ElementInterface $element): ?string {
    if (!self::isAnchorId($anchor)) {
      return $anchor;
    }

    $event = new AnchorEvent([
      'id' => substr($anchor, strlen(self::ID_PREFIX)),
      'element' => $element,
    ]);

    Event::trigger(AnchorEvent::class, self::EVENT_RESOLVE_ANCHOR_ID, $event);
    return $event->anchor;
  }
}
