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
  public $fragment;

  /**
   * @var string|null
   */
  public $host;

  /**
   * @var string|null
   */
  public $pass;

  /**
   * @var string|null
   */
  public $path;

  /**
   * @var string|null
   */
  public $port;

  /**
   * @var string|null
   */
  public $query;

  /**
   * @var string|null
   */
  public $scheme;

  /**
   * @var string|null
   */
  public $user;

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
      isset($this->user) ? $this->user : '',
      isset($this->pass) ? $this->pass : '',
    ]));
  }

  /**
   * @return string|null
   */
  public function getFragment() {
    return isset($this->fragment) ? (string)$this->fragment : null;
  }

  /**
   * @return array
   */
  public function getQuery() {
    if (!isset($this->query)) {
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
      unset($this->fragment);
    } else {
      $this->fragment = $fragment;
    }
  }

  /**
   * @param array $query
   */
  public function setQuery(array $query) {
    if (count($query) === 0) {
      unset($this->query);
    } else {
      $parts = [];
      foreach ($query as $key => $value) {
        $parts[] = $key . '=' . urlencode($value);
      }

      $this->query = implode('&', $parts);
    }
  }
}
