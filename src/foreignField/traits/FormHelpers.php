<?php

namespace lenz\craft\utils\foreignField\traits;

use Craft;
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

    $domain = $this->_field::TRANSLATION_DOMAIN;
    foreach ($options as $key => $value) {
      $options[$key] = Craft::t($domain, $value);
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
      ? Craft::t($this->_field::TRANSLATION_DOMAIN, $options[$value])
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
}
