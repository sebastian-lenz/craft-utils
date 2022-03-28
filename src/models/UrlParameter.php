<?php

namespace lenz\craft\utils\models;

/**
 * Class UrlParameter
 */
class UrlParameter
{
  /**
   * @var string
   */
  public string $value;


  /**
   * @param string $value
   */
  public function __construct(string $value) {
    $this->value = $value;
  }

  /**
   * @return string
   */
  public function __toString(): string {
    return $this->value;
  }
}
