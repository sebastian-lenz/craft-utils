<?php

namespace lenz\craft\utils\foreignField\listeners;

use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use yii\base\Event;

/**
 * Class RegisterCpTemplates
 */
class RegisterCpTemplates
{
  /**
   * @var bool
   */
  private static $_isRegistered;


  /**
   * @return void
   */
  public static function register() {
    if (!self::$_isRegistered) {
      self::$_isRegistered = true;

      Event::on(
        View::class,
        View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
        [RegisterCpTemplates::class, 'onRegisterCpTemplates']
      );
    }
  }

  /**
   * @return string
   */
  public static function getPath() {
    return dirname(__DIR__) . '/templates';
  }

  /**
   * @param RegisterTemplateRootsEvent $event
   */
  public static function onRegisterCpTemplates(RegisterTemplateRootsEvent $event) {
    $event->roots['foreign-field'] = self::getPath();
  }
}
