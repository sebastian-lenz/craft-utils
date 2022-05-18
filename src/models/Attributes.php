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
   * @var string
   */
  public string $charset;

  /**
   * @var array
   */
  public array $values;


  /**
   * @param array $values
   * @param string $charset
   */
  public function __construct(array $values = [], string $charset = 'utf-8') {
    parent::__construct('', $charset);

    $this->$charset = $charset;
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
   * @param string $className
   * @return $this
   */
  public function addClass(string $className): Attributes {
    $classNames = $this->getClasses();
    if (!in_array($className, $classNames)) {
      $classNames[] = $className;
      $this->set('class', $classNames);
    }

    return $this;
  }

  /**
   * @return int
   */
  public function count(): int {
    return mb_strlen($this->__toString(), $this->charset);
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

    return array_unique(
      array_filter(
        array_map('trim', $classNames)
      )
    );
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
    $classNames = $this->get('class', []);
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
   * @param string $className
   * @return $this
   */
  public function removeClass(string $className): Attributes {
    $classNames = $this->getClasses();
    $key = array_search($className, $classNames);
    if ($key !== false) {
      unset($classNames[$key]);
      $this->set('class', $classNames);
    }

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
}
