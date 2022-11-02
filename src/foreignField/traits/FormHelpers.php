<?php

/** @noinspection PhpUnused */

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
  abstract function attributeOptions(): array;

  /**
   * @param string $attribute
   * @return array
   */
  public function getAttributeOptions(string $attribute): array {
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
   * @noinspection PhpUnused (API)
   */
  public function getAttributeOptionLabel(string $attribute): string {
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
   * @param string $name
   * @param array $options
   * @return string
   * @noinspection PhpUnused (API)
   */
  public function renderRedactorField(string $name, array $options): string {
    $redactorClass = 'craft\redactor\Field';
    if (!class_exists($redactorClass)) {
      return '<p>Editor not available</p>';
    }

    $redactor = new $redactorClass([
      'handle' => $name,
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
  abstract protected function translate(string $message): string;
}
