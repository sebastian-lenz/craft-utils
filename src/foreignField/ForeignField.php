<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\db\ActiveRecord;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use Exception;
use Throwable;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Class ForeignField
 */
abstract class ForeignField extends Field
{
  /**
   * @inheritDoc
   * @throws Exception
   */
  public function afterElementSave(ElementInterface $element, bool $isNew) {
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
  public function behaviors() {
    return [
      'typecast' => [
        'class' => AttributeTypecastBehavior::class,
      ],
    ];
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
  public function getInputHtml($value, ElementInterface $element = null): string {
    return $this->getHtml($value, $element, false);
  }

  /**
   * @inheritDoc
   */
  public function getSettingsHtml() {
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
   */
  public function isAttributePropagated(string $attribute) {
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
  public function modifyElementsQuery(ElementQueryInterface $query, $value) {
    static::queryExtensionClass()::attachTo($query, $this, [
      'filters' => static::prepareQueryFilter($value),
    ]);

    return null;
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function modifyElementIndexQuery(ElementQueryInterface $query) {
    static::queryExtensionClass()::attachTo($query, $this, [
      'forceEagerLoad' => true,
    ]);
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function normalizeValue($value, ElementInterface $element = null) {
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
  public function serializeValue($value, ElementInterface $element = null) {
    return $this->toRecordAttributes($value, $element);
  }


  // Protected methods
  // -----------------

  /**
   * @param ActiveRecord $record
   * @param array $attributes
   * @param ElementInterface $element
   */
  protected function applyRecordAttributes(ActiveRecord $record, array $attributes, ElementInterface $element) {
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
   * @return ForeignFieldModel
   */
  protected function createModel(array $attributes = [], ElementInterface $element = null) {
    $modelClass = static::modelClass();
    return new $modelClass($this, $element, $attributes);
  }

  /**
   * @param ElementInterface $element
   * @return ActiveRecord
   * @throws Exception
   */
  protected function findOrCreateRecord(ElementInterface $element) {
    $record = $this->findRecord($element);

    if (is_null($record)) {
      /** @var ActiveRecord $recordClass */
      $recordClass = static::recordClass();
      $conditions = $this->getRecordConditions($element);
      $record = new $recordClass($conditions);
    }

    return $record;
  }

  /**
   * @param ElementInterface|null $element
   * @return ActiveRecord|null
   * @throws Exception
   */
  protected function findRecord(ElementInterface $element = null) {
    /** @var ActiveRecord $recordClass */
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
  protected function getRecordConditions(ElementInterface $element = null) {
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
   * @param ForeignFieldModel $value
   * @param ElementInterface|null $element
   * @param bool $disabled
   * @return string
   */
  protected function getHtml(ForeignFieldModel $value, ElementInterface $element = null, $disabled = false) {
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
   * @return mixed
   * @throws Exception
   */
  protected function prepareQueryFilter($value) {
    if (empty($value)) {
      return null;
    }

    if (!is_array($value)) {
      throw new Exception("The query value for the field {$this->handle} must be an array.");
    }

    $fields = static::recordModelAttributes();
    foreach ($value as $field => $condition) {
      if (!in_array($field, $fields)) {
        throw new Exception("The query for the field {$this->handle} refers to an unknown field '{$field}'.");
      }
    }

    return $value;
  }

  /**
   * @param string $template
   * @param array $variables
   * @return string
   */
  protected function render(string $template, array $variables) {
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
   * @param ForeignFieldModel $model
   * @param ElementInterface $element
   * @return bool
   */
  protected function shouldUpdateRecord(ForeignFieldModel $model, ElementInterface $element) {
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
   * @param ActiveRecord $record
   * @param ElementInterface|null $element
   * @return array
   */
  protected function toModelAttributes(ActiveRecord $record, ElementInterface $element = null) {
    return $record->getAttributes(static::recordModelAttributes());
  }

  /**
   * @param ForeignFieldModel $model
   * @param ElementInterface $element
   * @return array
   */
  protected function toRecordAttributes(ForeignFieldModel $model, ElementInterface $element) {
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
  abstract public static function inputTemplate(): string;

  /**
   * The model class used to represent this field.
   * @return string
   */
  abstract public static function modelClass(): string;

  /**
   * The query extension class used by this field.
   * @return string|ForeignFieldQueryExtension
   */
  public static function queryExtensionClass(): string {
    return ForeignFieldQueryExtension::class;
  }

  /**
   * The record class used to store this field.
   * @return string
   */
  abstract public static function recordClass(): string;

  /**
   * The list of record attributes that must be copied over to the model.
   * @return array
   */
  abstract public static function recordModelAttributes(): array;

  /**
   * @return string|null
   */
  public static function settingsTemplate() {
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
}

