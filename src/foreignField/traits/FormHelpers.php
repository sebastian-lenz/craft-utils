<?php

namespace lenz\craft\utils\foreignField\traits;

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
    $attributeOptions = $this->attributeOptions();
    $options = array_key_exists($attribute, $attributeOptions)
      ? $attributeOptions[$attribute]
      : [];

    foreach ($options as $key => $value) {
      $options[$key] = $this->translate($value);
    }

    return $options;
  }

  /**
   * @param string $attribute
   * @return string
   */
  public function getAttributeOptionLabel($attribute) {
    $value = $this->$attribute;
    $attributeOptions = $this->attributeOptions();
    $options = array_key_exists($attribute, $attributeOptions)
      ? $attributeOptions[$attribute]
      : [];

    return array_key_exists($value, $options)
      ? $this->translate($options[$value])
      : $value;
  }

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
  public function renderRedactorField(string $name, array $options) {
    $redactorClass = 'craft\redactor\Field';
    if (!class_exists($redactorClass)) {
      return '<p>Editor not available</p>';
    }

    $redactor = new $redactorClass([
      'handle'         => $name,
      'redactorConfig' => 'Custom.json',
    ] + $options);

    return $redactor->getInputHtml($this->$name);
  }


  // Protected methods
  // -----------------

  /**
   * @param string $message
   * @return string
   */
  abstract protected function translate(string $message);
}
