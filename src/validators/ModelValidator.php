<?php

/** @noinspection PhpUnused */

namespace lenz\craft\utils\validators;

use Craft;
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
  public ?string $invalid = null;

  /**
   * @var string
   */
  public string $modelClass = Model::class;


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
  protected function validateValue($value): ?array {
    if (!$value instanceof $this->modelClass) {
      return [$this->message, ['modelClass' => $this->modelClass]];
    }

    /** @var Model $value */
    if (!$value->validate()) {
      return [$this->invalid, ['error' => implode(' ', $value->getErrorSummary(true))]];
    }

    return null;
  }
}
