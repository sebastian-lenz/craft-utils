<?php

namespace lenz\craft\utils\foreignField\traits;

use Craft;
use craft\redactor\Field;
use lenz\craft\utils\foreignField\ForeignField;
use yii\base\Model;
use yii\validators\RequiredValidator;
use yii\validators\Validator;

/**
 * Trait FormHelpers
 * Helpers required by the form template provided by this module.
 */
trait FormHelpers
{
  /**
   * @return array
   */
  abstract function attributeOptions();

  /**
   * @param string $attribute
   * @return Validator[]
   * @see Model::getActiveValidators
   */
  abstract function getActiveValidators($attribute = null);

  /**
   * @param string $attribute
   * @return array
   */
  public function getAttributeOptions($attribute) {
    $field = $this->getField();
    $attributeOptions = $this->attributeOptions();
    $options = array_key_exists($attribute, $attributeOptions)
      ? $attributeOptions[$attribute]
      : [];

    foreach ($options as $key => $value) {
      $options[$key] = $field::t($value);
    }

    return $options;
  }

  /**
   * @param string $attribute
   * @return string
   */
  public function getAttributeOptionLabel($attribute) {
    $field = $this->getField();
    $value = $this->$attribute;
    $attributeOptions = $this->attributeOptions();
    $options = array_key_exists($attribute, $attributeOptions)
      ? $attributeOptions[$attribute]
      : [];

    return array_key_exists($value, $options)
      ? $field::t($options[$value])
      : $value;
  }

  /**
   * @return ForeignField
   */
  abstract function getField();

  /**
   * @param string $attribute
   * @return bool
   */
  public function isAttributeRequired($attribute) {
    $validators = $this->getActiveValidators($attribute);

    foreach ($validators as $validator) {
      if ($validator instanceof RequiredValidator) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param string $name
   * @param array $options
   * @return string
   */
  public function renderRedactorField($name, $options) {
    $redactorClass = 'craft\redactor\Field';
    if (!class_exists($redactorClass)) {
      return '<p>Editor not available</p>';
    }

    $redactor = new $redactorClass([
      'handle'         => $name,
      'redactorConfig' => 'Custom.json',
    ]);

    return $redactor->getInputHtml($this->$name);
  }
}
