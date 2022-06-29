<?php

/** @noinspection PhpUnused */

namespace lenz\craft\utils\elementCache;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\events\RegisterCacheOptionsEvent;
use craft\services\Elements;
use craft\services\Structures;
use craft\utilities\ClearCaches;
use Throwable;
use yii\base\Event;
use yii\caching\FileCache;
use yii\di\ServiceLocator;
use yii\web\BadRequestHttpException;

/**
 * Class ElementCache
 * A cache that will flush every time an element is changed.
 *
 * @property FileCache $cache
 */
class ElementCache extends ServiceLocator
{
  /**
   * @var bool
   */
  private static bool $_shouldUseCache;

  /**
   * @var ElementCache
   */
  private static ElementCache $_instance;


  /**
   * ElementCache constructor.
   */
  public function __construct() {
    parent::__construct();

    $this->components = [
      'cache' => [
        'class'     => FileCache::class,
        'cachePath' => '@runtime/element-cache'
      ],
    ];

    Event::on(
      ClearCaches::class,
      ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
      [$this, 'onRegisterCacheOptions']
    );

    $listener = [$this, 'onElementChanged'];
    Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, $listener);
    Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, $listener);
    Event::on(Elements::class, Elements::EVENT_AFTER_MERGE_ELEMENTS, $listener);
    Event::on(Structures::class, Structures::EVENT_AFTER_MOVE_ELEMENT, $listener);
  }

  /**
   * @return void
   */
  public function onElementChanged(): void {
    $this->cache->flush();
  }

  /**
   * @param RegisterCacheOptionsEvent $event
   */
  public function onRegisterCacheOptions(RegisterCacheOptionsEvent $event): void {
    $event->options[] = [
      'key'    => 'elements',
      'label'  => 'Elements',
      'action' => [$this->cache, 'flush']
    ];
  }


  // Static methods
  // --------------

  /**
   * @return FileCache
   */
  static function getCache(): FileCache {
    return self::getInstance()->cache;
  }

  /**
   * @return ElementCache
   */
  public static function getInstance(): ElementCache {
    if (!isset(self::$_instance)) {
      self::$_instance = new ElementCache();
    }

    return self::$_instance;
  }

  /**
   * @return bool
   * @throws BadRequestHttpException
   */
  static public function shouldUseCache(): bool {
    if (!isset(self::$_shouldUseCache)) {
      $request = Craft::$app->getRequest();
      self::$_shouldUseCache = (
        is_null($request->getToken()) &&
        !$request->getIsPreview() &&
        !$request->getIsLivePreview()
      );
    }

    return self::$_shouldUseCache;
  }

  /**
   * @param string|array $key
   * @param callable $callback
   * @return mixed
   */
  static public function with(string|array $key, callable $callback): mixed {
    if (!self::shouldUseCache()) {
      return $callback();
    }

    return self::getInstance()->cache->getOrSet($key, $callback);
  }

  /**
   * @param string|array $key
   * @param ElementInterface $element
   * @param callable $callback
   * @return mixed
   */
  static public function withElement(string|array $key, ElementInterface $element, callable $callback): mixed {
    $key = is_string($key) ? [$key] : $key;
    $key[] = 'element=' . $element->getId();
    if ($element instanceof Element) {
      $key[] = 'siteId=' . $element->siteId;
    }

    return self::with($key, $callback);
  }

  /**
   * @param string|array $key
   * @param callable $callback
   * @return mixed
   */
  static public function withLanguage(string|array $key, callable $callback): mixed {
    try {
      $site = Craft::$app->getSites()->getCurrentSite();
      $key = is_string($key) ? [$key] : $key;
      $key[] = 'language=' . $site->language;
    } catch (Throwable) {
      return $callback();
    }

    return self::with($key, $callback);
  }

  /**
   * @param string|array $key
   * @param callable $callback
   * @return mixed
   */
  static public function withSite(string|array $key, callable $callback): mixed {
    try {
      $site = Craft::$app->getSites()->getCurrentSite();
      $key = is_string($key) ? [$key] : $key;
      $key[] = 'site=' . $site->handle;
    } catch (Throwable) {
      return $callback();
    }

    return self::with($key, $callback);
  }
}
