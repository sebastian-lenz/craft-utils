<?php

namespace lenz\craft\utils\models;

use yii\base\Model;
use yii\validators\Validator;

/**
 * Class ModelValidator
 */
class ModelValidator extends Validator
{
  /**
   * @var string|null
   */
  public $invalid = null;

  /**
   * @var string
   */
  public $modelClass = Model::class;


  /**
   * @inheritdoc
   */
  public function init() {
    parent::init();

    if ($this->message === null) {
      $this->message = Craft::t('app', '{attribute} must be an instance of {modelClass}.');
    }

    if ($this->invalid === null) {
      $this->invalid = Craft::t('app', '{attribute} is invalid: {error}');
    }
  }

  /**
   * @inheritDoc
   */
  protected function validateValue($value) {
    if (!$value instanceof $this->$modelClass) {
      return [$this->message, ['modelClass' => $this->modelClass]];
    }

    /** @var Model $value */
    if (!$value->validate()) {
      return [$this->invalid, ['error' => implode(' ', $value->getErrorSummary())]];
    }

    return null;
  }
}
