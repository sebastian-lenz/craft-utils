<?php

namespace lenz\craft\utils\elementCache;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\events\ElementEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\services\Elements;
use craft\utilities\ClearCaches;
use Throwable;
use yii\base\Event;
use yii\caching\FileCache;
use yii\di\ServiceLocator;

/**
 * Class ElementCache
 * A cache that will flush every time an element is changed.
 *
 * @property FileCache $cache
 */
class ElementCache extends ServiceLocator
{
  /**
   * @var ElementCache
   */
  private static $_instance;


  /**
   * ElementCache constructor.
   */
  private function __construct() {
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
  }

  /**
   * @param ElementEvent $event
   */
  public function onElementChanged(Event $event) {
    $this->cache->flush();
  }

  /**
   * @param RegisterCacheOptionsEvent $event
   */
  public function onRegisterCacheOptions(RegisterCacheOptionsEvent $event) {
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
  static function getCache() {
    return self::getInstance()->cache;
  }

  /**
   * @return ElementCache
   */
  public static function getInstance() {
    if (!isset(self::$_instance)) {
      self::$_instance = new ElementCache();
    }

    return self::$_instance;
  }

  /**
   * @param string $key
   * @param callable $callback
   * @return mixed
   */
  static public function with(string $key, callable $callback) {
    return self::getInstance()->cache->getOrSet($key, $callback);
  }

  /**
   * @param string $key
   * @param ElementInterface $element
   * @param callable $callback
   * @return mixed
   */
  static public function withElement(string $key, ElementInterface $element, callable $callback) {
    $key .= ';element=' . $element->getId();
    if ($element instanceof Element) {
      $key .= ';site=' . $element->siteId;
    }

    return self::with($key, $callback);
  }

  /**
   * @param string $key
   * @param callable $callback
   * @return mixed
   */
  static public function withLanguage(string $key, callable $callback) {
    try {
      $site = Craft::$app->getSites()->getCurrentSite();
      $key .= ';language=' . $site->language;
    } catch (Throwable $error) {
      Craft::error($error->getMessage());
    }

    return self::with($key, $callback);
  }
}
