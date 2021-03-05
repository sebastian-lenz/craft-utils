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
   * @param object|array $array
   * @param string|Closure|array $key
   * @param mixed $default
   * @return mixed
   */
  static public function get($array, $key, $default = null) {
    try {
      return self::getValue($array, $key, $default);
    } catch (Throwable $error) {
      return $default;
    }
  }
}
