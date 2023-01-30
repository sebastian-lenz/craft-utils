<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use Exception;
use Throwable;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Class ForeignField
 *
 * The following methods are intended to be overwritten by child classes:
 * - inputTemplate
 * - modelClass
 * - recordClass
 * - recordModelAttributes
 *
 * @template TModel of ForeignFieldModel
 * @template TRecord of ForeignFieldRecord
 */
abstract class ForeignField extends Field
{
  /**
   * @inheritDoc
   * @throws Exception
   */
  public function afterElementSave(ElementInterface $element, bool $isNew): void {
    $model = $element->getFieldValue($this->handle);
    if (!is_a($model, static::modelClass())) {
      throw new Exception('Invalid value');
    }

    $model = $model->withOwner($element);

    if ($this->shouldUpdateRecord($model, $element)) {
      $attributes = $this->toRecordAttributes($model, $element);
      $record     = $this->findOrCreateRecord($element);

      $this->applyRecordAttributes($record, $attributes, $element);
      $record->save();
    }

    parent::afterElementSave($element, $isNew);
  }

  /**
   * @inheritDoc
   */
  public function behaviors(): array {
    $behaviors = parent::behaviors();
    
    $behaviors['typecast'] = [
      'class' => AttributeTypecastBehavior::class,
    ];
    
    return $behaviors;
  }

  /**
   * @inheritDoc
   */
  public function getElementValidationRules(): array {
    return [
      [ForeignFieldModelValidator::class, 'field' => $this]
    ];
  }

  /**
   * @inheritDoc
   */
  public function getInputHtml($value, ?ElementInterface $element = null): string {
    return $this->getHtml($value, $element);
  }

  /**
   * @inheritDoc
   */
  public function getSettingsHtml(): ?string {
    $template = static::settingsTemplate();
    if (empty($template)) {
      return null;
    }

    return $this->render($template, [
      'field'  => $this,
      'name'   => is_null($this->handle) ? uniqid() : $this->handle,
    ]);
  }

  /**
   * @inheritDoc
   * @throws Throwable
   */
  public function getStaticHtml($value, ElementInterface $element): string {
    return $this->getHtml($value, $element, true);
  }

  /**
   * @param string $attribute
   * @return bool
   * @noinspection PhpUnusedParameterInspection
   */
  public function isAttributePropagated(string $attribute): bool {
    return true;
  }

  /**
   * @inheritdoc
   */
  public function isValueEmpty($value, ElementInterface $element): bool {
    return (
      !is_a($value, static::modelClass()) ||
      ($value instanceof ForeignFieldModel && $value->isEmpty())
    );
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function modifyElementsQuery(ElementQueryInterface $query, $value): void {
    static::queryExtensionClass()::attachTo($query, $this, [
      'filters' => static::prepareQueryFilter($value),
    ]);
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function modifyElementIndexQuery(ElementQueryInterface $query): void {
    static::queryExtensionClass()::attachTo($query, $this, [
      'forceEagerLoad' => true,
    ]);
  }

  /**
   * @param mixed $value
   * @param ElementInterface|null $element
   * @return TModel
   * @throws Exception
   */
  public function normalizeValue(mixed $value, ?ElementInterface $element = null): ForeignFieldModel {
    $modelClass = static::modelClass();
    if (is_a($value, $modelClass)) {
      return $value;
    }

    if (is_array($value)) {
      $attributes = $value;
    } elseif (is_string($value)) {
      try {
        $attributes = Json::decode($value);
      } catch (Throwable $error) {
        Craft::error($error->getMessage());
        $attributes = null;
      }

      if (!is_array($attributes)) {
        $attributes = [];
      }
    } else {
      $record = $this->findRecord($element);
      $attributes = is_null($record)
        ? []
        : $this->toModelAttributes($record, $element);
    }

    return $this->createModel($attributes, $element);
  }

  /**
   * @inheritdoc
   */
  public function serializeValue(mixed $value, ?ElementInterface $element = null): string {
    return Json::encode($this->toRecordAttributes($value, $element));
  }


  // Protected methods
  // -----------------

  /**
   * @param TRecord $record
   * @param array $attributes
   * @param ElementInterface $element
   */
  protected function applyRecordAttributes(ForeignFieldRecord $record, array $attributes, ElementInterface $element): void {
    $isPropagating =
      $element instanceof Element &&
      $element->propagating &&
      !$record->isNewRecord;

    foreach ($attributes as $name => $value) {
      // Skip all attributes that are not propagated
      if ($isPropagating && !$this->isAttributePropagated($name)) {
        continue;
      }

      $record->setAttribute($name, $value);
    }
  }

  /**
   * @param array $attributes
   * @param ElementInterface|null $element
   * @return TModel
   */
  protected function createModel(array $attributes = [], ElementInterface $element = null): ForeignFieldModel {
    $modelClass = static::modelClass();
    return new $modelClass($this, $element, $attributes);
  }

  /**
   * @param ElementInterface $element
   * @return TRecord
   * @throws Exception
   */
  protected function findOrCreateRecord(ElementInterface $element): ForeignFieldRecord {
    $record = $this->findRecord($element);
    if (is_null($record)) {
      $recordClass = static::recordClass();
      $conditions = $this->getRecordConditions($element);
      $record = new $recordClass($conditions);
    }

    return $record;
  }

  /**
   * @param ElementInterface|null $element
   * @return TRecord|null
   * @throws Exception
   */
  protected function findRecord(ElementInterface $element = null): ?ForeignFieldRecord {
    $recordClass = static::recordClass();
    $conditions = $this->getRecordConditions($element);

    return is_null($conditions)
      ? null
      : $recordClass::findOne($conditions);
  }

  /**
   * @param ElementInterface|null $element
   * @return array|null
   * @throws Exception
   */
  protected function getRecordConditions(ElementInterface $element = null): ?array {
    if (is_null($element)) {
      return null;
    }

    $conditions = [
      'elementId' => $element->getId(),
      'fieldId'   => $this->id,
    ];

    if (static::hasPerSiteRecords()) {
      if (!$element instanceof Element) {
        throw new Exception('Cannot reference elements not extending class Element.');
      }

      $conditions['siteId'] = $element->siteId;
    }

    return $conditions;
  }

  /**
   * @param TModel $value
   * @param ElementInterface|null $element
   * @param bool $disabled
   * @return string
   */
  protected function getHtml(ForeignFieldModel $value, ElementInterface $element = null, bool $disabled = false): string {
    /**
     * Craft 3.5 introduces a strange behaviour that calls the render function
     * on another instance of the field than the field has been created with.
     * We reroute this call to the actual field the value has been created with.
     * @see https://github.com/craftcms/cms/issues/6478
     */
    $actualField = $value->getField();
    if ($actualField !== $this) {
      return $actualField->getHtml($value, $element, $disabled);
    }

    return $this->render(static::inputTemplate(), [
      'disabled' => $disabled,
      'element'  => $element,
      'field'    => $this,
      'name'     => is_null($this->handle) ? uniqid() : $this->handle,
      'value'    => $value,
    ]);
  }

  /**
   * @param mixed $value
   * @return array|null
   * @throws Exception
   */
  protected function prepareQueryFilter(mixed $value): ?array {
    if (empty($value)) {
      return null;
    }

    if (!is_array($value)) {
      throw new Exception("The query value for the field $this->handle must be an array.");
    }

    $fields = static::recordModelAttributes();
    foreach ($value as $field => $condition) {
      if (!in_array($field, $fields)) {
        throw new Exception("The query for the field $this->handle refers to an unknown field '$field'.");
      }
    }

    return $value;
  }

  /**
   * @param string $template
   * @param array $variables
   * @return string
   */
  protected function render(string $template, array $variables): string {
    try {
      return Craft::$app->getView()->renderTemplate($template, $variables);
    } catch (Throwable $error) {
      return implode('', [
        '<div class="readable">',
          '<blockquote class="note">',
            '<p><strong>Error while rendering template</strong></p>',
            '<p>', $error->getMessage(), '</p>',
            '<p>In ', $error->getFile(), ' at line ', $error->getLine(), '</p>',
          '</blockquote>',
        '</div>'
      ]);
    }
  }

  /**
   * @param TModel $model
   * @param ElementInterface $element
   * @return bool
   * @noinspection PhpUnusedParameterInspection
   */
  protected function shouldUpdateRecord(ForeignFieldModel $model, ElementInterface $element): bool {
    if (
      !static::hasPerSiteRecords() &&
      $element instanceof Element &&
      $element->propagating
    ) {
      return false;
    }

    return true;
  }

  /**
   * @param TRecord $record
   * @param ElementInterface|null $element
   * @return array
   * @noinspection PhpUnusedParameterInspection
   */
  protected function toModelAttributes(ForeignFieldRecord $record, ElementInterface $element = null): array {
    return $record->getAttributes(static::recordModelAttributes());
  }

  /**
   * @param TModel $model
   * @param ElementInterface $element
   * @return array
   * @noinspection PhpUnusedParameterInspection
   */
  protected function toRecordAttributes(ForeignFieldModel $model, ElementInterface $element): array {
    return $model->getAttributes(static::recordModelAttributes());
  }


  // Static methods
  // --------------

  /**
   * @inheritDoc
   */
  public static function hasContentColumn(): bool {
    return false;
  }

  /**
   * @return bool
   */
  public static function hasPerSiteRecords(): bool {
    return true;
  }

  /**
   * @return string
   */
  public static function inputTemplate(): string {
    return '';
  }

  /**
   * The model class used to represent this field.
   * @return class-string<TModel>
   */
  public static function modelClass(): string {
    return ForeignFieldModel::class;
  }

  /**
   * The query extension class used by this field.
   * @return class-string<ForeignFieldQueryExtension>
   */
  public static function queryExtensionClass(): string {
    return ForeignFieldQueryExtension::class;
  }

  /**
   * The record class used to store this field.
   * @return class-string<TRecord>
   */
  public static function recordClass(): string {
    return ForeignFieldRecord::class;
  }

  /**
   * The list of record attributes that must be copied over to the model.
   * @return array
   */
  public static function recordModelAttributes(): array {
    return [];
  }

  /**
   * @return string|null
   */
  public static function settingsTemplate(): ?string {
    return null;
  }

  /**
   * @return array
   */
  public static function supportedTranslationMethods(): array {
    if (!static::hasPerSiteRecords()) {
      return [
        self::TRANSLATION_METHOD_NONE,
      ];
    }

    return [
      self::TRANSLATION_METHOD_NONE,
      self::TRANSLATION_METHOD_SITE,
      self::TRANSLATION_METHOD_SITE_GROUP,
      self::TRANSLATION_METHOD_LANGUAGE,
      self::TRANSLATION_METHOD_CUSTOM,
    ];
  }

  /**
   * @param string $message
   * @return string
   */
  public static function t(string $message): string {
    return Craft::t('site', $message);
  }

  /**
   * @inheritdoc
   */
  public static function valueType(): string {
    return static::modelClass();
  }
}
