<?php

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace lenz\craft\utils\helpers;

use Craft;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\imagetransforms\ImageTransformer;
use craft\models\ImageTransformIndex;
use yii\base\InvalidConfigException;

/**
 * Class ImageTransforms
 */
class ImageTransforms extends \craft\helpers\ImageTransforms
{
  /**
   * @param Asset $asset
   * @param ImageTransformIndex $index
   * @return string
   * @throws ImageTransformException
   */
  static public function ensureTransformUrlByIndexModel(Asset $asset, ImageTransformIndex $index): string {
    return ImageTransforms\ReadableTransformer::getInstance()
      ->ensureTransformUrlByIndexModel($asset, $index);
  }

  /**
   * @return ImageTransformer
   * @throws InvalidConfigException
   * @noinspection PhpUnused
   */
  static public function getTransformer(): ImageTransformer {
    return Craft::$app->imageTransforms->getImageTransformer(ImageTransformer::class);
  }

  /**
   * @param Asset $asset
   * @param ImageTransformIndex $transformIndex
   * @return string|null
   * @noinspection PhpUnused
   */
  static public function getTransformPath(Asset $asset, ImageTransformIndex $transformIndex): ?string {
    return ImageTransforms\ReadableTransformer::getInstance()
      ->getTransformPath($asset, $transformIndex);
  }
}
