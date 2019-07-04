<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\db\ActiveRecord;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use Exception;
use Yii;

/**
 * Class ForeignFieldQueryExtension
 */
class ForeignFieldQueryExtension
{
  /**
   * @var bool
   */
  public $enableEagerLoad;

  /**
   * @var bool
   */
  public $enableJoin;

  /**
   * @var ForeignField
   */
  public $field;

  /**
   * @var array|null
   */
  public $filters;

  /**
   * @var ElementQuery
   */
  public $query;


  /**
   * ForeignFieldQueryExtension constructor.
   * @param array $config
   */
  public function __construct(array $config) {
    Yii::configure($this, $config);

    $this->query->on(
      ElementQuery::EVENT_AFTER_PREPARE,
      [$this, 'onAfterPrepare']
    );
  }

  /**
   * @return void
   */
  public function onAfterPrepare() {
    $enableEagerLoad = (
      $this->enableEagerLoad &&
      !static::isCountQuery($this->query)
    );

    if ($enableEagerLoad || $this->enableJoin) {
      $this->attachJoin();
    }

    if ($enableEagerLoad) {
      $this->attachEagerLoad();
    }
  }


  // Protected methods
  // -----------------

  /**
   * @return void
   */
  protected function attachEagerLoad() {
    $this->query->query->addSelect([
      'field:' . $this->field->handle => $this->getJsonExpression(),
    ]);
  }

  /**
   * @return void
   */
  protected function attachJoin() {
    /** @var ActiveRecord $recordClass */
    $recordClass = $this->field::recordClass();
    $tableName   = $recordClass::tableName();
    $handle      = $this->field->handle;
    $fieldId     = $this->field->id;
    $query       = $this->query->query;
    $subQuery    = $this->query->subQuery;

    $conditions = [
      "[[$handle.elementId]] = [[elements.id]]",
      "[[$handle.fieldId]] = $fieldId"
    ];

    if ($this->field::hasPerSiteRecords()) {
      $conditions[] = "[[$handle.siteId]] = [[elements_sites.siteId]]";
    }

    $conditionsList = implode(' AND ', $conditions);

    $query->leftJoin($tableName . ' ' . $handle, $conditionsList);
    $subQuery->leftJoin($tableName . ' ' . $handle, $conditionsList);

    if (is_array($this->filters)) {
      foreach ($this->filters as $name => $filter) {
        $subQuery->andWhere(Db::parseParam("[[$handle.$name]]", $filter));
      }
    }
  }

  /**
   * @return string
   */
  protected function getJsonExpression() {
    $attributes  = $this->field::recordModelAttributes();
    $handle      = $this->field->handle;
    $fields      = [];

    foreach ($attributes as $attribute) {
      $fields[] = '"' . $attribute . '"';
      $fields[] = "[[$handle.$attribute]]";
    }

    $fieldsList = implode(', ', $fields);
    $jsonFunction = Craft::$app->getDb()->getIsMysql()
      ? 'json_object'
      : 'json_build_object';

    return "IF([[$handle.id]] IS NULL, NULL, {$jsonFunction}({$fieldsList}))";
  }


  // Static public methods
  // ---------------------

  /**
   * @param ElementQueryInterface $query
   * @param ForeignField $field
   * @param mixed $filters
   * @param bool $forceEagerLoad
   * @throws Exception
   */
  static public function attachTo(ElementQueryInterface $query, ForeignField $field, $filters, $forceEagerLoad = false) {
    if (!($query instanceof ElementQuery)) {
      return;
    }

    if (empty($filters)) {
      $filters = null;
    } elseif (!is_array($filters)) {
      throw new Exception("The query value for the field {$field->handle} must be an array.");
    }

    $enableEagerLoad = static::enableEagerLoad($query, $field) || $forceEagerLoad;
    $enableJoin = static::enableJoin($query, $field) || $filters;

    if ($enableEagerLoad || $enableJoin) {
      new static([
        'enableEagerLoad' => $enableEagerLoad,
        'enableJoin'      => $enableJoin,
        'field'           => $field,
        'filters'         => $filters,
        'query'           => $query,
      ]);
    }
  }

  /**
   * @param ElementQuery $query
   * @return bool
   */
  static public function isCountQuery(ElementQuery $query) {
    return (
      count($query->select) == 1 &&
      reset($query->select) == 'COUNT(*)'
    );
  }


  // Static protected methods
  // ------------------------

  /**
   * @param ElementQuery $query
   * @param ForeignField $field
   * @return bool
   */
  static protected function enableEagerLoad(ElementQuery $query, ForeignField $field) {
    $handle = $field->handle;
    if ($query->with == $handle) {
      $query->with = null;
      return true;
    }

    if (is_array($query->with) && in_array($handle, $query->with)) {
      ArrayHelper::removeValue($query->with, $handle);
      return true;
    }

    return false;
  }

  /**
   * @param ElementQuery $query
   * @param ForeignField $field
   * @return bool
   */
  static protected function enableJoin(ElementQuery $query, ForeignField $field) {
    $items = array_merge(
      is_array($query->orderBy) ? $query->orderBy : [(string)$query->orderBy],
      is_array($query->groupBy) ? $query->groupBy : [(string)$query->groupBy]
    );

    foreach ($items as $item) {
      if (strpos($item, $field->handle) !== false) {
        return true;
      }
    }

    return false;
  }
}
