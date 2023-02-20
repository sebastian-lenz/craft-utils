<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\base\ElementInterface;
use craft\base\Model;
use craft\elements\MatrixBlock;
use Exception;
use lenz\craft\utils\helpers\ArrayHelper;
use lenz\craft\utils\helpers\ElementHelpers;
use yii\base\InvalidConfigException;

/**
 * Class ForeignModel
 */
abstract class ForeignFieldModel extends Model
{
  /**
   * @var ForeignField
   */
  protected ForeignField $_field;

  /**
   * @var ElementInterface|null
   */
  protected ?ElementInterface $_owner;

  /**
   * @var ElementInterface|null|false
   */
  protected null|false|ElementInterface $_root = false;


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
  public function __debugInfo(): array {
    return $this->attributes;
  }

  /**
   * @param string $attribute
   * @return string
   */
  public function getAttributeLabel($attribute): string {
    return $this->translate(parent::getAttributeLabel($attribute));
  }

  /**
   * @return ForeignField
   */
  public function getField(): ForeignField {
    return $this->_field;
  }

  /**
   * @return ElementInterface|null
   * @noinspection PhpUnused (API)
   */
  public function getOwner(): ElementInterface|null {
    return $this->_owner;
  }

  /**
   * @return ElementInterface|null
   * @throws InvalidConfigException
   */
  public function getRoot(): ElementInterface|null {
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
  public function isEmpty(): bool {
    return false;
  }

  /**
   * @param ElementInterface|null $owner
   * @return $this
   * @noinspection PhpUnused (API)
   */
  public function withOwner(ElementInterface $owner = null): static {
    if ($this->_owner === $owner) {
      return $this;
    }

    $model = clone $this;
    $model->_owner = $owner;
    $model->_root = false;
    return $model;
  }

  /**
   * @return array
   */
  public function __serialize() : array {
    return [
      '_attributes' => $this->attributes,
      '_field' => $this->_field->handle,
      '_owner' => ElementHelpers::serialize($this->_owner),
    ];
  }

  /**
   * @param array $data
   * @throws Exception
   */
  public function __unserialize(array $data): void {
    $this->_owner = ElementHelpers::unserialize(
      ArrayHelper::get($data, '_owner')
    );

    /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
    $this->_field = Craft::$app->getFields()->getFieldByHandle(
      (string)ArrayHelper::get($data, '_field', ''),
      $this->_owner->getFieldContext() ?? null
    );

    $attributes = ArrayHelper::get($data, '_attributes');
    if (is_array($attributes)) {
      $this->setAttributes($attributes, false);
    }
  }


  // Protected methods
  // -----------------

  /**
   * @param string $message
   * @return string
   */
  protected function translate(string $message): string {
    return $this->_field::t($message);
  }


  // Private methods
  // ---------------

  /**
   * @param ElementInterface $element
   * @return ElementInterface
   * @throws InvalidConfigException
   */
  private function getParentElement(ElementInterface $element): ElementInterface {
    if ($element instanceof MatrixBlock) {
      return $this->getParentElement($element->getOwner());
    }

    if (is_a($element, 'verbb\supertable\elements\SuperTableBlockElement')) {
      /** @noinspection PhpPossiblePolymorphicInvocationInspection */
      return $this->getParentElement($element->getOwner());
    }

    return $element;
  }
}
