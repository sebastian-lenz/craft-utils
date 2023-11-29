<?php

namespace lenz\craft\utils\models;

use craft\helpers\Html;
use InvalidArgumentException;
use Twig\Markup;

/**
 * Class Attributes
 */
class Attributes extends Markup
{
  /**
   * @var array
   */
  public array $values;


  /**
   * @param array $values
   */
  public function __construct(array $values = []) {
    parent::__construct('', 'utf-8');

    $this->values = $values;
  }

  /**
   * @param string $name
   * @return mixed
   */
  public function __get(string $name): mixed {
    return $this->get($name);
  }

  /**
   * @param string $name
   * @return bool
   */
  public function __isset(string $name): bool {
    return $this->has($name);
  }

  /**
   * @param string $name
   * @param mixed $value
   */
  public function __set(string $name, mixed $value) {
    $this->set($name, $value);
  }

  /**
   * @param string $name
   */
  public function __unset(string $name) {
    $this->remove($name);
  }

  /**
   * @return string
   */
  public function __toString(): string {
    return Html::renderTagAttributes($this->values);
  }

  /**
   * @param array|string $nameOrValues
   * @param mixed|null $value
   * @return Attributes
   */
  public function add(array|string $nameOrValues, mixed $value = null): Attributes {
    return $this->set($nameOrValues, $value);
  }

  /**
   * @param array|string|null $classNames
   * @return $this
   */
  public function addClass(array|string|null $classNames): Attributes {
    $classNames = self::splitClassNames($classNames);
    $existing = $this->getClasses();

    foreach ($classNames as $className) {
      if (!in_array($className, $existing)) {
        $existing[] = $className;
      }
    }

    $this->set('class', $existing);
    return $this;
  }

  /**
   * @return int
   */
  public function count(): int {
    return mb_strlen($this->__toString(), 'utf-8');
  }

  /**
   * @param string $name
   * @param mixed $defaultValue
   * @return mixed
   */
  public function get(string $name, mixed $defaultValue = null): mixed {
    return array_key_exists($name, $this->values)
      ? $this->values[$name]
      : $defaultValue;

  }

  /**
   * @return string[]
   */
  public function getClasses(): array {
    $classNames = $this->get('class', []);
    if (!is_array($classNames)) {
      $classNames = explode(' ', $classNames);
    }

    return self::splitClassNames($classNames);
  }

  /**
   * @param string $name
   * @return bool
   */
  public function has(string $name): bool {
    return array_key_exists($name, $this->values);
  }

  /**
   * @param string $className
   * @return bool
   */
  public function hasClass(string $className): bool {
    $classNames = $this->getClasses();
    return in_array($className, $classNames);
  }

  /**
   * @return string
   */
  public function jsonSerialize(): string {
    return $this->__toString();
  }

  /**
   * @param string $name
   * @return $this
   */
  public function remove(string $name): Attributes {
    unset($this->values[$name]);
    return $this;
  }

  /**
   * @param array|string|null $classNames
   * @return $this
   */
  public function removeClass(array|string|null $classNames): Attributes {
    $classNames = self::splitClassNames($classNames);
    $existing = $this->getClasses();

    foreach ($classNames as $className) {
      $key = array_search($className, $existing);
      if ($key !== false) {
        unset($existing[$key]);
      }
    }

    $this->set('class', array_values($existing));
    return $this;
  }

  /**
   * @param string|array $nameOrValues
   * @param mixed $value
   * @return $this
   */
  public function set(string|array $nameOrValues, mixed $value = null): Attributes {
    if (is_string($nameOrValues)) {
      $this->values[$nameOrValues] = $value;
    } elseif (is_iterable($nameOrValues)) {
      foreach ($nameOrValues as $key => $keyValue) {
        $this->values[$key] = $keyValue;
      }
    } else {
      throw new InvalidArgumentException();
    }

    return $this;
  }

  /**
   * @param string $className
   * @param bool|null $value
   * @return $this
   * @noinspection PhpUnused (API)
   */
  public function toggleClass(string $className, ?bool $value = null): Attributes {
    if (is_null($value)) {
      $value = !$this->hasClass($className);
    }

    return $value
      ? $this->addClass($className)
      : $this->removeClass($className);
  }


  // Static methods
  // --------------

  /**
   * @param mixed $value
   * @return Attributes
   */
  static public function create(mixed $value = []): Attributes {
    if ($value instanceof Attributes) {
      return $value;
    } elseif (is_array($value)) {
      return new Attributes($value);
    }

    return new Attributes();
  }

  /**
   * @param array|string|null $classNames
   * @return array
   */
  static private function splitClassNames(array|string|null $classNames): array {
    if (empty($classNames)) {
      return [];
    }

    return array_values(array_unique(array_filter(array_map('trim',
      is_string($classNames) ? explode(' ', $classNames) : $classNames
    ))));
  }
}
