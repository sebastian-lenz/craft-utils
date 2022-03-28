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
  public ?string $fragment = null;

  /**
   * @var string|null
   */
  public ?string $host = null;

  /**
   * @var string|null
   */
  public ?string $pass = null;

  /**
   * @var string|null
   */
  public ?string $path = null;

  /**
   * @var string|null
   */
  public ?string $port = null;

  /**
   * @var string|null
   */
  public ?string $query = null;

  /**
   * @var string|null
   */
  public ?string $scheme = null;

  /**
   * @var string|null
   */
  public ?string $user = null;

  /**
   * @var array
   */
  const DEFAULT_GLUES = [
    ['scheme',   '',  '://'],
    ['auth',     '',  '@'],
    ['host',     '',  ''],
    ['port',     ':', ''],
    ['path',     '',  ''],
    ['query',    '?', ''],
    ['fragment', '#', ''],
  ];

  /**
   * @var array
   */
  const MAILTO_GLUES = [
    ['scheme', '',  ':'],
    ['path',   '',  ''],
    ['query',  '?', ''],
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
  public function __toString(): string {
    $result = [];
    $glues = $this->isMailTo() ? self::MAILTO_GLUES : self::DEFAULT_GLUES;
    $parts = $this->attributes + [
      'auth' => $this->getAuthentication(),
    ];

    foreach ($glues as list($key, $prefix, $suffix)) {
      $value = $parts[$key] ?? '';
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
  public function getAuthentication(): string {
    return implode(':', array_filter([
      empty($this->user) ? '' : $this->user,
      empty($this->pass) ? '' : $this->pass,
    ]));
  }

  /**
   * @return string|null
   */
  public function getFragment(): ?string {
    return empty($this->fragment) ? null : (string)$this->fragment;
  }

  /**
   * @return array
   */
  public function getQuery(): array {
    if (empty($this->query)) {
      return [];
    }

    $result = [];
    $isMailTo = $this->isMailTo();

    foreach (explode('&', $this->query) as $param) {
      $parts = explode('=', $param, 2);
      if (count($parts) !== 2) {
        continue;
      }

      list($key, $value) = $parts;
      $value = $isMailTo ? rawurldecode($value) : urldecode($value);
      $result[urldecode($key)] = $value;
    }

    return $result;
  }

  /**
   * @return bool
   */
  public function isMailTo(): bool {
    return $this->scheme == 'mailto';
  }

  /**
   * @param array $query
   * @return $this
   */
  public function mergeQuery(array $query): Url {
    return $this->setQuery(array_merge($query, $this->getQuery()));
  }

  /**
   * @param string|null $fragment
   * @return $this
   * @noinspection PhpUnused (API)
   */
  public function setFragment(string $fragment = null): static {
    if (empty($fragment)) {
      $this->fragment = null;
    } else {
      $this->fragment = $fragment;
    }

    return $this;
  }

  /**
   * @param array $query
   * @return $this
   */
  public function setQuery(array $query): Url {
    $parts = [];
    $isMailTo = $this->isMailTo();

    foreach ($query as $key => $value) {
      if (is_null($value)) {
        continue;
      }

      if ($value instanceof UrlParameter) {
        $value = $value->value;
      } elseif ($isMailTo) {
        $value = rawurlencode($value);
      } else {
        $value = urlencode($value);
      }

      $parts[] = urlencode($key) . '=' . $value;
    }

    $this->query = empty($parts) ? null : implode('&', $parts);
    return $this;
  }


  // Static methods
  // --------------

  /**
   * @param string $url
   * @param array $query
   * @return string
   */
  static public function compose(string $url, array $query = []): string {
    return (string)Url::create($url)->mergeQuery($query);
  }

  /**
   * @param string $url
   * @return Url
   */
  static public function create(string $url): Url {
    return new Url($url);
  }
}
