<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use yii\validators\Validator;

/**
 * Class ForeignModelValidator
 */
class ForeignModelValidator extends Validator
{
  /**
   * @var string
   */
  public $modelClass = ForeignModel::class;

  /**
   * @var string
   */
  public $msgInstanceOf = '{attribute} must be an instance of {modelClass}.';

  /**
   * @var string
   */
  public $msgErrors = '{attribute} contains errors.';

  /**
   * @var string
   */
  public $translationDomain = 'site';


  /**
   * @param mixed $value
   * @return array|null
   */
  protected function validateValue($value) {
    if (!is_a($value, $this->modelClass)) {
      return [
        Craft::t($this->translationDomain, $this->msgInstanceOf),
        ['modelClass' => $this->modelClass]
      ];
    }

    if (!$value->validate()) {
      return [Craft::t($this->translationDomain, $this->msgErrors), []];
    }

    return null;
  }
}
