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
  private $_element;

  /**
   * @var int
   */
  private $_elementId;

  /**
   * @var int|null
   */
  private $_siteId;

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
  public function __construct($config = []) {
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
  public function getElementId() {
    return $this->_elementId;
  }

  /**
   * @return Site|null
   */
  public function getSite() {
    $element = $this->getElement();
    return $element ? $element->getSite() : null;
  }


  // Private methods
  // ---------------

  /**
   * @param array $config
   * @return array
   */
  private function extractElementConfig(array $config) {
    $element = isset($config['element']) ? $config['element'] : null;

    if ($element instanceof ElementInterface) {
      $this->_element = $element;
      $this->_elementId = $element->id;
      $this->_siteId = $element->siteId;
    }
    elseif (isset($config['elementId'])) {
      $this->_elementId = $config['elementId'];
      $this->_siteId = isset($config['siteId']) ? $config['siteId'] : null;
    }
    else {
      throw new InvalidArgumentException('Either `element` or `elementId` must be set.');
    }

    return array_diff_key($config, self::ELEMENT_CONFIG);
  }
}
