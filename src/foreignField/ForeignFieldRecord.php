<?php

namespace lenz\craft\utils\foreignField;

use craft\db\ActiveRecord;
use craft\db\Migration;
use craft\db\Table;

/**
 * Class ForeignFieldRecord
 * @property int $id
 * @property int $elementId
 * @property int $fieldId
 * @property int $siteId
 */
abstract class ForeignFieldRecord extends ActiveRecord
{
  /**
   * Whether the content should be stored on a per site basis.
   */
  const IS_PER_SITE = true;

  /**
   * The name of the related database table.
   */
  const TABLE_NAME = '{{%noop}}';


  /**
   * @return array
   */
  public function getModelAttributes() {
    $attributes = $this->modelAttributes();

    return array_filter($this->getAttributes(), function($key) use ($attributes) {
      return in_array($key, $attributes);
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * @return array
   */
  public function modelAttributes() {
    return array_diff($this->attributes(), [
      'dateCreated',
      'dateUpdated',
      'elementId',
      'fieldId',
      'id',
      'siteId',
      'uid',
    ]);
  }

  /**
   * @inheritDoc
   */
  public function rules() {
    $result = [
      [['elementId', 'fieldId', 'id'], 'integer'],
      [['elementId', 'fieldId'], 'required'],
    ];

    if (static::IS_PER_SITE) {
      $result[] = ['siteId', 'integer'];
      $result[] = ['siteId', 'required'];
    }

    return $result;
  }

  /**
   * @param array $values
   * @return $this
   */
  public function setModelAttributes($values) {
    $attributes = $this->modelAttributes();

    foreach ($attributes as $attribute) {
      if (array_key_exists($attribute, $values)) {
        $this->$attribute = $values[$attribute];
      }
    }

    return $this;
  }


  // Static methods
  // --------------

  /**
   * @param Migration $migration
   * @param array $columns
   */
  public static function createTable(Migration $migration, array $columns) {
    $table = static::tableName();
    if ($migration->db->tableExists($table)) {
      return;
    }

    $baseColumns = [
      'id'        => $migration->primaryKey(),
      'elementId' => $migration->integer()->notNull(),
      'fieldId'   => $migration->integer()->notNull(),
    ];

    if (static::IS_PER_SITE) {
      $baseColumns['siteId'] = $migration->integer()->notNull();
    }

    $columns = array_merge($baseColumns, $columns, [
      'dateCreated' => $migration->dateTime()->notNull(),
      'dateUpdated' => $migration->dateTime()->notNull(),
      'uid'         => $migration->uid(),
    ]);

    $migration->createTable($table, $columns);

    if (static::IS_PER_SITE) {
      $migration->createIndex(null, $table, ['elementId', 'siteId', 'fieldId'], true);
      $migration->createIndex(null, $table, ['fieldId'], false);
      $migration->createIndex(null, $table, ['siteId'], false);
    } else {
      $migration->createIndex(null, $table, ['elementId', 'fieldId'], true);
      $migration->createIndex(null, $table, ['fieldId'], false);
    }

    $migration->addForeignKey(null, $table, ['elementId'], Table::ELEMENTS, ['id'], 'CASCADE', null);
    $migration->addForeignKey(null, $table, ['fieldId'], Table::FIELDS, ['id'], 'CASCADE', null);
  }

  /**
   * @param Migration $migration
   */
  public static function dropTable(Migration $migration) {
    $table = static::tableName();
    if ($migration->db->tableExists($table)) {
      $migration->dropTable($table);
    }
  }

  /**
   * @inheritDoc
   */
  public static function tableName() {
    return static::TABLE_NAME;
  }
}
