<?php

namespace lenz\craft\utils\models;

use yii\base\Model;

/**
 * Class Url
 */
class Url extends Model
{
  /**
   * @var string|null
   */
  public $fragment = null;

  /**
   * @var string|null
   */
  public $host = null;

  /**
   * @var string|null
   */
  public $pass = null;

  /**
   * @var string|null
   */
  public $path = null;

  /**
   * @var string|null
   */
  public $port = null;

  /**
   * @var string|null
   */
  public $query = null;

  /**
   * @var string|null
   */
  public $scheme = null;

  /**
   * @var string|null
   */
  public $user = null;

  /**
   * @var array
   */
  const GLUES = [
    ['scheme',   '',  '://'],
    ['auth',     '',  '@'],
    ['host',     '',  ''],
    ['port',     ':', ''],
    ['path',     '',  ''],
    ['query',    '?', ''],
    ['fragment', '#', ''],
  ];


  /**
   * Url constructor.
   * @param string $url
   */
  public function __construct(string $url) {
    parent::__construct(parse_url($url));
  }

  /**
   * @return string
   */
  public function __toString() {
    $result = [];
    $parts = $this->attributes + [
      'auth' => $this->getAuthentication(),
    ];

    foreach (self::GLUES as list($key, $prefix, $suffix)) {
      $value = isset($parts[$key]) ? $parts[$key] : '';
      if (!empty($value)) {
        array_push($result, $prefix, $value, $suffix);
      }
    }

    if (isset($parts['host']) && !isset($parts['scheme'])) {
      array_unshift($result, '//');
    }

    return implode('', $result);
  }

  /**
   * @return string
   */
  public function getAuthentication() {
    return implode(':', array_filter([
      empty($this->user) ? '' : $this->user,
      empty($this->pass) ? '' : $this->pass,
    ]));
  }

  /**
   * @return string|null
   */
  public function getFragment() {
    return empty($this->fragment) ? null : (string)$this->fragment;
  }

  /**
   * @return array
   */
  public function getQuery() {
    if (empty($this->query)) {
      return [];
    }

    $result = array();
    foreach (explode('&', $this->query) as $param) {
      $parts = explode('=', $param, 2);
      if (count($parts) !== 2) {
        continue;
      }

      list($key, $value) = $parts;
      $result[$key] = urldecode($value);
    }

    return $result;
  }

  /**
   * @param string|null $fragment
   */
  public function setFragment(string $fragment = null) {
    if (empty($fragment)) {
      $this->fragment = null;
    } else {
      $this->fragment = $fragment;
    }
  }

  /**
   * @param array $query
   */
  public function setQuery(array $query) {
    if (empty($query)) {
      $this->query = null;
    } else {
      $parts = [];
      foreach ($query as $key => $value) {
        $parts[] = $key . '=' . urlencode($value);
      }

      $this->query = implode('&', $parts);
    }
  }
}
