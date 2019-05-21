<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\MatrixBlock;

/**
 * Class ForeignModel
 */
abstract class ForeignModel extends Model
{
  /**
   * @var ForeignField
   */
  protected $_field;

  /**
   * @var ElementInterface|null
   */
  protected $_owner;

  /**
   * @var ElementInterface
   */
  protected $_root;


  /**
   * ForeignModel constructor.
   * @param ForeignField $field
   * @param ElementInterface|null $owner
   * @param array $config
   */
  public function __construct(ForeignField $field, ElementInterface $owner = null, array $config = []) {
    parent::__construct($config);

    $this->_field = $field;
    $this->_owner = $owner;
  }

  /**
   * @param string $attribute
   * @return string
   */
  public function getAttributeLabel($attribute) {
    return Craft::t(
      $this->_field::TRANSLATION_DOMAIN,
      parent::getAttributeLabel($attribute)
    );
  }

  /**
   * @return ForeignField
   */
  public function getField() {
    return $this->_field;
  }

  /**
   * @return ElementInterface|null
   */
  public function getOwner() {
    return $this->_owner;
  }

  /**
   * @return ElementInterface
   */
  public function getRoot() {
    if (!isset($this->_rootElement)) {
      $this->_rootElement = $this->getParentElement($this->_owner);
    }

    return $this->_rootElement;
  }

  /**
   * @return bool
   */
  public function isEmpty() {
    return false;
  }


  // Private methods
  // ---------------

  /**
   * @param ElementInterface $element
   * @return ElementInterface
   */
  private function getParentElement(ElementInterface $element) {
    if ($element instanceof MatrixBlock) {
      return $this->getParentElement($element->getOwner());
    }

    if (is_a($element, 'verbb\supertable\elements\SuperTableBlockElement')) {
      return $this->getParentElement($element->getOwner());
    }

    return $element;
  }
}
