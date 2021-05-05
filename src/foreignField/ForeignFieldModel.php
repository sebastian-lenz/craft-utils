<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\MatrixBlock;
use lenz\craft\utils\helpers\ArrayHelper;
use lenz\craft\utils\helpers\ElementHelpers;
use Serializable;
use yii\base\InvalidConfigException;

/**
 * Class ForeignModel
 */
abstract class ForeignFieldModel extends Model implements Serializable
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
   * @return array
   */
  public function __debugInfo() {
    return $this->attributes;
  }

  /**
   * @param string $attribute
   * @return string
   * @noinspection PhpMissingParamTypeInspection
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
   * @throws InvalidConfigException
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
   * @inheritDoc
   */
  public function serialize() {
    return serialize($this->getSerializedData());
  }

  /**
   * @inheritDoc
   */
  public function unserialize($data) {
    $data = unserialize($data);
    $this->setSerializedData(is_array($data) ? $data : []);
  }

  /**
   * @param ElementInterface|null $owner
   * @return $this
   */
  public function withOwner(ElementInterface $owner = null) {
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
   * @return array
   */
  protected function getSerializedData() : array {
    return [
      '_attributes' => $this->attributes,
      '_field' => $this->_field->handle,
      '_owner' => ElementHelpers::serialize($this->_owner),
    ];
  }

  /**
   * @param array $data
   */
  protected function setSerializedData(array $data) {
    $this->_field = Craft::$app->getFields()->getFieldByHandle(
      (string)ArrayHelper::get($data, '_field', '')
    );

    $this->_owner = ElementHelpers::unserialize(
      ArrayHelper::get($data, '_owner')
    );

    $attributes = ArrayHelper::get($data, '_attributes');
    if (is_array($attributes)) {
      $this->setAttributes($attributes, false);
    }
  }

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
   * @throws InvalidConfigException
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
