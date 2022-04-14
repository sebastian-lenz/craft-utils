<?php

namespace lenz\craft\utils\events;

use craft\base\ElementInterface;
use yii\base\Event;

/**
 * Class AnchorsEvent
 */
class AnchorsEvent extends AbstractElementEvent
{
  /**
   * @var array
   */
  private array $_anchors = [];

  /**
   * @var string
   */
  const EVENT_FIND_ANCHORS = 'findAnchors';


  /**
   * @param string $anchor  The anchor as it appears in the document (the id or fragment)
   * @param string|null $title  A human-readable label for the anchor
   * @param string|null $id  A unique id that identifies this anchor
   * @noinspection PhpUnused (API)
   */
  public function addAnchor(string $anchor, string $title = null, string $id = null) {
    if (!is_null($id) && !AnchorEvent::isAnchorId($id)) {
      $id = AnchorEvent::ID_PREFIX . $id;
    }

    $this->_anchors[] = [
      'anchor' => $anchor,
      'id' => $id,
      'title' => empty($title) ? $anchor : $title,
    ];
  }

  /**
   * @return array
   */
  public function getAnchors(): array {
    return $this->_anchors;
  }


  // Static methods
  // --------------

  /**
   * @param ElementInterface $element
   * @return array
   */
  static public function findAnchors(ElementInterface $element): array {
    $event = new AnchorsEvent([
      'element' => $element,
    ]);

    Event::trigger(AnchorsEvent::class, self::EVENT_FIND_ANCHORS, $event);
    return $event->getAnchors();
  }

  /**
   * @param int|string $elementId
   * @param int|string|null $siteId
   * @return array
   * @noinspection PhpUnused (API)
   */
  static public function findAnchorsById(int|string $elementId, int|string $siteId = null): array {
    $event = new AnchorsEvent([
      'elementId' => $elementId,
      'siteId' => $siteId,
    ]);

    Event::trigger(AnchorsEvent::class, self::EVENT_FIND_ANCHORS, $event);
    return $event->getAnchors();
  }
}
