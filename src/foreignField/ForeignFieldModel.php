<?php

namespace lenz\craft\utils\foreignField;

use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\MatrixBlock;

/**
 * Class ForeignModel
 */
abstract class ForeignFieldModel extends Model
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
   * @var ElementInterface|null|false
   */
  protected $_root = false;


  /**
   * ForeignModel constructor.
   *
   * @param ForeignField $field
   * @param ElementInterface|null $owner
   * @param array $config
   */
  public function __construct(ForeignField $field, ElementInterface $owner = null, array $config = []) {
    $this->_field = $field;
    $this->_owner = $owner;

    parent::__construct($config);
  }

  /**
   * @param string $attribute
   * @return string
   */
  public function getAttributeLabel($attribute) {
    return $this->translate(parent::getAttributeLabel($attribute));
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
   * @return ElementInterface|null
   */
  public function getRoot() {
    if ($this->_root === false) {
      $this->_root = is_null($this->_owner)
        ? null
        : $this->getParentElement($this->_owner);
    }

    return $this->_root;
  }

  /**
   * @return bool
   */
  public function isEmpty() {
    return false;
  }

  /**
   * @param ElementInterface|null $owner
   * @return $this
   */
  public function withOwner($owner) {
    if ($this->_owner === $owner) {
      return $this;
    }

    $model = clone $this;
    $model->_owner = $owner;
    $model->_root = false;
    return $model;
  }


  // Protected methods
  // -----------------

  /**
   * @param string $message
   * @return string
   */
  protected function translate(string $message) {
    return $this->_field::t($message);
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
