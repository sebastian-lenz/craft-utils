<?php

namespace lenz\craft\utils\foreignField;

use craft\db\ActiveRecord;
use craft\db\Migration;
use craft\db\Table;
use craft\records\Element;
use craft\records\Field;
use craft\records\Site;
use yii\db\ActiveQueryInterface;

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
   * @return ActiveQueryInterface
   */
  public function getElement(): ActiveQueryInterface {
    return $this->hasOne(Element::class, ['id' => 'elementId']);
  }

  /**
   * @return ActiveQueryInterface
   */
  public function getField(): ActiveQueryInterface {
    return $this->hasOne(Field::class, ['id' => 'fieldId']);
  }

  /**
   * @return ActiveQueryInterface
   */
  public function getSite(): ActiveQueryInterface {
    return $this->hasOne(Site::class, ['id' => 'siteId']);
  }

  /**
   * @inheritDoc
   */
  public function rules(): array {
    return [
      [['elementId', 'fieldId', 'id', 'siteId'], 'integer'],
      [['elementId', 'fieldId', 'siteId'], 'required'],
    ];
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

    $columns = array_merge(
      [
        'id'          => $migration->primaryKey(),
        'elementId'   => $migration->integer()->notNull(),
        'fieldId'     => $migration->integer()->notNull(),
        'siteId'      => $migration->integer()->notNull(),
      ],
      $columns,
      [
        'dateCreated' => $migration->dateTime()->notNull(),
        'dateUpdated' => $migration->dateTime()->notNull(),
        'uid'         => $migration->uid(),
      ]
    );

    $migration->createTable($table, $columns);

    $migration->createIndex(null, $table, ['elementId', 'siteId', 'fieldId'], true);
    $migration->createIndex(null, $table, ['fieldId']);
    $migration->createIndex(null, $table, ['siteId']);

    $migration->addForeignKey(null, $table, ['elementId'], Table::ELEMENTS, ['id'], 'CASCADE');
    $migration->addForeignKey(null, $table, ['fieldId'], Table::FIELDS, ['id'], 'CASCADE');
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
}
