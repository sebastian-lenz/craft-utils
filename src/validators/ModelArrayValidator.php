<?php

/** @noinspection PhpUnused */

namespace lenz\craft\utils\validators;

use Craft;
use craft\validators\ArrayValidator;
use yii\base\Model;

/**
 * Class ModelArrayValidator
 */
class ModelArrayValidator extends ArrayValidator
{
  /**
   * @var string|null
   */
  public ?string $instance = null;

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
  public function init(): void {
    parent::init();

    if ($this->instance === null) {
      $this->instance = Craft::t('app', 'Member #{index} of {attribute} must be an instance of {modelClass}.');
    }

    if ($this->invalid === null) {
      $this->invalid = Craft::t('app', 'Member #{index} of {attribute} is invalid: {error}');
    }
  }

  /**
   * @inheritDoc
   */
  protected function validateValue($value): ?array {
    $result = parent::validateValue($value);
    if (!is_null($result)) {
      return $result;
    }

    foreach ($value as $index => $member) {
      if (!$member instanceof $this->modelClass) {
        return [$this->message, [
          'index' => $index,
          'modelClass' => $this->modelClass
        ]];
      }

      /** @var Model $member */
      if (!$member->validate()) {
        return [$this->invalid, [
          'index' => $index,
          'error' => implode(' ', $member->getErrorSummary(true))
        ]];
      }
    }

    return null;
  }
}
