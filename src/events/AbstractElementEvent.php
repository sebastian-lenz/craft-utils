<?php

namespace lenz\craft\utils\events;

use Craft;
use craft\base\ElementInterface;
use craft\models\Site;
use InvalidArgumentException;
use yii\base\Event;

/**
 * Class AbstractElementEvent
 */
abstract class AbstractElementEvent extends Event
{
  /**
   * @var ElementInterface|null
   */
  private ?ElementInterface $_element;

  /**
   * @var int
   */
  private int $_elementId;

  /**
   * @var int|null
   */
  private ?int $_siteId;

  /**
   * @var array
   */
  const ELEMENT_CONFIG = [
    'element' => true,
    'elementId' => true,
    'siteId' => true,
  ];


  /**
   * AbstractElementEvent constructor.
   * @param array $config
   */
  public function __construct(array $config = []) {
    parent::__construct(
      $this->extractElementConfig($config)
    );
  }

  /**
   * @return ElementInterface|null
   */
  public function getElement(): ?ElementInterface {
    if (!isset($this->_element)) {
      $this->_element = Craft::$app->getElements()->getElementById(
        $this->_elementId, null, $this->_siteId
      );
    }

    return $this->_element;
  }

  /**
   * @return int
   */
  public function getElementId(): int {
    return $this->_elementId;
  }

  /**
   * @return Site|null
   */
  public function getSite(): ?Site {
    return $this->getElement()?->getSite();
  }


  // Private methods
  // ---------------

  /**
   * @param array $config
   * @return array
   */
  private function extractElementConfig(array $config): array {
    $element = $config['element'] ?? null;

    if ($element instanceof ElementInterface) {
      $this->_element = $element;
      $this->_elementId = $element->id;
      $this->_siteId = $element->siteId;
    }
    elseif (isset($config['elementId'])) {
      $this->_elementId = $config['elementId'];
      $this->_siteId = $config['siteId'] ?? null;
    }
    else {
      throw new InvalidArgumentException('Either `element` or `elementId` must be set.');
    }

    return array_diff_key($config, self::ELEMENT_CONFIG);
  }
}
