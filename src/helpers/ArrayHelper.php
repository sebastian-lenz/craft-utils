<?php

namespace lenz\craft\utils\helpers;

use Closure;
use craft\helpers\ArrayHelper as ArrayHelperBase;
use Throwable;

/**
 * Class ArrayHelper
 */
class ArrayHelper extends ArrayHelperBase
{
  /**
   * @param iterable $array
   * @param callable $callback
   * @return mixed
   */
  static public function firstResult(iterable $array, callable $callback): mixed {
    foreach ($array as $index => $value) {
      $result = $callback($value, $index);
      if (!is_null($result)) {
        return $result;
      }
    }

    return null;
  }

  /**
   * @param object|array $array
   * @param string|Closure|array $key
   * @param mixed $default
   * @return mixed
   */
  static public function get(mixed $array, mixed $key, mixed $default = null): mixed {
    try {
      return self::getValue($array, $key, $default);
    } catch (Throwable) {
      return $default;
    }
  }
}
