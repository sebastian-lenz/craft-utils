<?php

namespace lenz\craft\utils\foreignField;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\Model;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
use Exception;
use Throwable;

/**
 * Class AbstractRecordField
 */
abstract class ForeignField extends Field
{
  /**
   * The model class used to represent this field.
   */
  const MODEL_CLASS = Model::class;

  /**
   * The query extension class used by this field.
   */
  const QUERY_EXTENSION_CLASS = ForeignFieldQueryExtension::class;

  /**
   * The record class used to store this field.
   */
  const RECORD_CLASS = ForeignFieldRecord::class;

  /**
   * The template used to render the field input.
   */
  const TEMPLATE_INPUT = '';

  /**
   * The template used to render the field settings.
   */
  const TEMPLATE_SETTINGS = '';

  /**
   * The translation domain used by this field.
   */
  const TRANSLATION_DOMAIN = 'site';


  /**
   * @inheritDoc
   * @throws Exception
   */
  public function afterElementSave(ElementInterface $element, bool $isNew) {
    $model = $element->getFieldValue($this->handle);
    if (!($model instanceof ForeignModel)) {
      throw new Exception('Invalid value');
    }

    if ($this->shouldUpdateRecord($element, $model)) {
      $attributes = $this->getRecordAttributes($model, $element);
      $this->findOrCreateRecord($element)
        ->setModelAttributes($attributes)
        ->save();
    }

    parent::afterElementSave($element, $isNew);
  }

  /**
   * @inheritDoc
   */
  public function getElementValidationRules(): array {
    return [
      [ForeignModelValidator::class,
        'modelClass'        => static::MODEL_CLASS,
        'translationDomain' => static::TRANSLATION_DOMAIN,
      ]
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
    if (empty(static::TEMPLATE_SETTINGS)) {
      return null;
    }

    return $this->render(static::TEMPLATE_SETTINGS, [
      'field' => $this,
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
      !is_a($value, static::MODEL_CLASS) ||
      ($value instanceof ForeignModel && $value->isEmpty())
    );
  }

  /**
   * @param ElementQueryInterface $query
   * @param mixed $value
   * @return bool|false|null
   * @throws Exception
   */
  public function modifyElementsQuery(ElementQueryInterface $query, $value) {
    /** @var ForeignFieldQueryExtension $queryExtension */
    $queryExtension = static::QUERY_EXTENSION_CLASS;
    $queryExtension::attachTo($query, $this, $value);

    return null;
  }

  /**
   * @param ElementQueryInterface $query
   * @throws Exception
   */
  public function modifyElementIndexQuery(ElementQueryInterface $query) {
    /** @var ForeignFieldQueryExtension $queryExtension */
    $queryExtension = static::QUERY_EXTENSION_CLASS;
    $queryExtension::attachTo($query, $this, null, true);
  }

  /**
   * @inheritDoc
   */
  public function normalizeValue($value, ElementInterface $element = null) {
    $modelClass = static::MODEL_CLASS;
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
      }

      if (!is_array($attributes)) {
        $attributes = [];
      }
    } else {
      $record = $this->findRecord($element);
      $attributes = is_null($record)
        ? []
        : $record->getModelAttributes();
    }

    return $this->createModel($attributes, $element);
  }


  // Protected methods
  // -----------------

  /**
   * @param array $attributes
   * @param ElementInterface|null $element
   * @return ForeignModel
   */
  protected function createModel(array $attributes = [], ElementInterface $element = null) {
    $modelClass = static::MODEL_CLASS;
    return new $modelClass($this, $element, $attributes);
  }

  /**
   * @param ElementInterface $element
   * @return ForeignFieldRecord
   */
  protected function findOrCreateRecord(ElementInterface $element) {
    $record = $this->findRecord($element);

    if (is_null($record)) {
      /** @var ForeignFieldRecord $recordClass */
      $recordClass = static::RECORD_CLASS;
      $conditions = $this->getRecordConditions($element);
      $record = new $recordClass($conditions);
    }

    return $record;
  }

  /**
   * @param ElementInterface|null $element
   * @return ForeignFieldRecord|null
   */
  protected function findRecord(ElementInterface $element = null) {
    /** @var ForeignFieldRecord $recordClass */
    $recordClass = static::RECORD_CLASS;
    $conditions = $this->getRecordConditions($element);

    return is_null($conditions)
      ? null
      : $recordClass::findOne($conditions);
  }

  /**
   * @param ForeignModel $model
   * @param ElementInterface $element
   * @return array
   */
  public function getRecordAttributes(ForeignModel $model, ElementInterface $element) {
    $attributes = $model->getAttributes();

    if (
      $element instanceof Element &&
      $element->propagating
    ) {
      $propagatingAttributes = [];
      foreach ($attributes as $attribute => $value) {
        if ($this->isAttributePropagated($attribute)) {
          $propagatingAttributes[$attribute] = $value;
        }
      }

      $attributes = $propagatingAttributes;
    }

    return $attributes;
  }

  /**
   * @param ElementInterface|null $element
   * @return array|null
   */
  protected function getRecordConditions(ElementInterface $element = null) {
    if (is_null($element)) {
      return null;
    }

    $conditions = [
      'elementId' => $element->getId(),
      'fieldId'   => $this->id,
    ];

    if (static::hasPerSiteRecords() && $element instanceof Element) {
      $conditions['siteId'] = $element->siteId;
    }

    return $conditions;
  }

  /**
   * @param ForeignModel $value
   * @param ElementInterface|null $element
   * @param bool $disabled
   * @return string
   */
  protected function getHtml(ForeignModel $value, ElementInterface $element = null, $disabled = false) {
    return $this->render(static::TEMPLATE_INPUT, [
      'disabled' => $disabled,
      'element'  => $element,
      'field'    => $this,
      'name'     => $this->handle,
      'value'    => $value,
    ]);
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
   * @param ElementInterface $element
   * @param ForeignModel $model
   * @return bool
   */
  protected function shouldUpdateRecord(ElementInterface $element, ForeignModel $model) {
    /** @var ForeignFieldRecord $recordClass */
    $recordClass = static::RECORD_CLASS;

    if (
      $element instanceof Element &&
      $element->propagating &&
      !$recordClass::IS_PER_SITE
    ) {
      return false;
    }

    return true;
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
    /** @var ForeignFieldRecord $recordClass */
    $recordClass = static::RECORD_CLASS;
    return $recordClass::IS_PER_SITE;
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
}

