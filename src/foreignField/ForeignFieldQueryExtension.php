<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\db\ActiveRecord;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\events\CancelableEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use Exception;
use Yii;
use yii\base\Event;
use yii\db\Expression;
use yii\db\ExpressionInterface;

/**
 * Class ForeignFieldQueryExtension
 */
class ForeignFieldQueryExtension
{
  /**
   * @phpstan-var bool
   */
  public bool $enableEagerLoad;

  /**
   * @phpstan-var bool
   */
  public bool $enableJoin;

  /**
   * @phpstan-var ForeignField
   */
  public ForeignField $field;

  /**
   * @phpstan-var array|null
   */
  public ?array $filters;

  /**
   * @phpstan-var ElementQuery
   */
  public ElementQuery $query;

  /**
   * @phpstan-var ForeignFieldQueryExtension[]
   */
  private static array $INSTANCES = array();


  /**
   * ForeignFieldQueryExtension constructor.
   * @phpstan-param array $config
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
   * @phpstan-return void
   */
  public function onAfterPrepare(): void {
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
   * @phpstan-param array $options
   * @phpstan-return $this
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
   * @phpstan-return void
   */
  protected function attachEagerLoad(): void {
    $this->query->query->addSelect([
      'field:' . $this->field->handle => $this->getJsonExpression(),
    ]);
  }

  /**
   * @phpstan-return void
   */
  protected function attachJoin(): void {
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
   * @phpstan-return string
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
   * @phpstan-param ElementQueryInterface $query
   * @phpstan-param ForeignField $field
   * @phpstan-param array $options
   * @phpstan-return ForeignFieldQueryExtension|void|null
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
        if ($instance->query === $query && $instance->field === $field) {
          return $instance->applyOptions($options);
        }
      }

      return new static($options);
    }

    return null;
  }

  /**
   * @phpstan-param ElementQuery $query
   * @phpstan-return bool
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
   * @phpstan-param ElementQuery $query
   * @phpstan-param ForeignField $field
   * @phpstan-return bool
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
   * @phpstan-param ElementQuery $query
   * @phpstan-param ForeignField $field
   * @phpstan-return bool
   */
  static protected function enableJoin(ElementQuery $query, ForeignField $field): bool {
    $values = array_merge(
      is_array($query->orderBy) ? $query->orderBy : [$query->orderBy],
      is_array($query->groupBy) ? $query->groupBy : [$query->groupBy],
      self::findFieldsInWhere($query->where),
    );

    $values = array_filter(
      array_merge(array_values($values), array_keys($values)),
      fn($value) => is_string($value) && !empty($value)
    );

    foreach ($values as $value) {
      if (str_contains($value, $field->handle)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param array|string|ExpressionInterface|null $value
   * @return array|string[]
   */
  static protected function findFieldsInWhere(array|null|string|ExpressionInterface $value): array {
    if ($value instanceof Expression) {
      return [$value->expression];
    } elseif (is_string($value)) {
      return [$value];
    } elseif (is_array($value)) {
      return array_map(self::findFieldsInWhere(...), $value);
    } else {
      return [];
    }
  }


  // Static methods
  // --------------

  /**
   * @phpstan-return void
   */
  static public function init(): void {
    static $isInitialized;
    if (isset($isInitialized)) return;
    $isInitialized = true;

    Event::on(ElementQuery::class, ElementQuery::EVENT_BEFORE_PREPARE, function(CancelableEvent $event) {
      $query = $event->sender;
      if (!($query instanceof ElementQuery) || !$query->withCustomFields) {
        return;
      }

      $fieldAttributes = $query->getBehavior('customFields');
      $fieldLayouts = Craft::$app->getFields()->getLayoutsByType($query->elementType);
      $fields = array_filter(array_unique(array_reduce($fieldLayouts,
        fn($fields, $fieldLayout) => array_merge($fields, $fieldLayout->getCustomFields()),
        [])),
        fn($field) => $field instanceof ForeignField
      );

      foreach ($fields as $field) {
        $queryClass = $field::queryExtensionClass();
        $handle = $field->handle;
        $filter = $fieldAttributes->$handle ?? null;

        $queryClass::attachTo($query, $field, [
          'filters' => $queryClass::prepareQueryFilter($field, $filter),
        ]);
      }
    });
  }


  /**
   * @phpstan-param ForeignField $field
   * @phpstan-param mixed $value
   * @phpstan-return array|null
   * @throws Exception
   */
  static function prepareQueryFilter(ForeignField $field, mixed $value): ?array {
    if (empty($value)) {
      return null;
    }

    if (!is_array($value)) {
      throw new Exception("The query value for the field $field->handle must be an array.");
    }

    $attributes = $field::recordModelAttributes();
    foreach ($value as $attribute => $condition) {
      if (!in_array($attribute, $attributes)) {
        throw new Exception("The query for the field $field->handle refers to an unknown field '$attribute'.");
      }
    }

    return $value;
  }
}
