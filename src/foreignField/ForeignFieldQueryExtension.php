<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\db\ActiveRecord;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
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
  public bool $enableEagerLoad;

  /**
   * @var bool
   */
  public bool $enableJoin;

  /**
   * @var ForeignField
   */
  public ForeignField $field;

  /**
   * @var array|null
   */
  public ?array $filters;

  /**
   * @var ElementQuery
   */
  public ElementQuery $query;

  /**
   * @var ForeignFieldQueryExtension[]
   */
  private static array $INSTANCES = array();


  /**
   * ForeignFieldQueryExtension constructor.
   * @param array $config
   */
  public final function __construct(array $config) {
    self::$INSTANCES[] = $this;
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
      $this->enableEagerLoad && (
        !empty($this->filters) ||
        !static::isCountQuery($this->query)
      )
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
   * @param array $options
   * @return $this
   */
  protected function applyOptions(array $options): static {
    $this->enableEagerLoad = $this->enableEagerLoad || $options['enableEagerLoad'];
    $this->enableJoin = $this->enableJoin || $options['enableJoin'];

    if (is_array($options['filters'])) {
      $this->filters = is_array($this->filters)
        ? array_merge($this->filters, $options['filters'])
        : $options['filters'];
    }

    return $this;
  }

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
  protected function getJsonExpression(): string {
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

    return "IF([[$handle.id]] IS NULL, NULL, $jsonFunction($fieldsList))";
  }


  // Static public methods
  // ---------------------

  /**
   * @param ElementQueryInterface $query
   * @param ForeignField $field
   * @param array $options
   * @return ForeignFieldQueryExtension|void|null
   * @throws Exception
   * @noinspection PhpUnused (API)
   */
  static public function attachTo(ElementQueryInterface $query, ForeignField $field, array $options = []) {
    if (!($query instanceof ElementQuery)) {
      return;
    }

    $filters = ArrayHelper::getValue($options, 'filters');
    $forceEagerLoad = !!ArrayHelper::getValue($options, 'forceEagerLoad', false);
    $forceJoin = !!ArrayHelper::getValue($options, 'forceJoin', false);

    $enableEagerLoad = static::enableEagerLoad($query, $field) || $forceEagerLoad;
    $enableJoin = static::enableJoin($query, $field) || $filters || $forceJoin;

    if ($enableEagerLoad || $enableJoin) {
      $options = [
        'enableEagerLoad' => $enableEagerLoad,
        'enableJoin' => $enableJoin,
        'field' => $field,
        'filters' => $filters,
        'query' => $query,
      ];

      foreach (self::$INSTANCES as $instance) {
        if ($instance->query === $query) {
          return $instance->applyOptions($options);
        }
      }

      return new static($options);
    }

    return null;
  }

  /**
   * @param ElementQuery $query
   * @return bool
   */
  static public function isCountQuery(ElementQuery $query): bool {
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
  static protected function enableEagerLoad(ElementQuery $query, ForeignField $field): bool {
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
  static protected function enableJoin(ElementQuery $query, ForeignField $field): bool {
    $items = array_merge(
      is_array($query->orderBy) ? $query->orderBy : [$query->orderBy],
      is_array($query->groupBy) ? $query->groupBy : [$query->groupBy],
      [Json::encode($query->where)]
    );

    foreach ($items as $key => $value) {
      if (
        str_contains($key, $field->handle) ||
        str_contains($value, $field->handle)
      ) {
        return true;
      }
    }

    return false;
  }
}
