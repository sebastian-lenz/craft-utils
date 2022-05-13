<?php

namespace lenz\craft\utils\helpers\ImageTransforms;

use craft\elements\Asset;
use craft\imagetransforms\ImageTransformer;
use craft\models\ImageTransformIndex;
use yii\base\InvalidConfigException;

/**
 * Class ReadableTransformer
 */
class ReadableTransformer extends ImageTransformer
{
  /**
   * @inheritDoc
   */
  public function ensureTransformUrlByIndexModel(Asset $asset, ImageTransformIndex $index): string {
    return parent::ensureTransformUrlByIndexModel($asset, $index);
  }

  /**
   * @param Asset $asset
   * @param ImageTransformIndex $transformIndex
   * @return string|null
   */
  public function getTransformPath(Asset $asset, ImageTransformIndex $transformIndex): ?string {
    try {
      return $this->getTransformBasePath($asset) . $this->getTransformSubpath($asset, $transformIndex);
    } catch (InvalidConfigException) {
      return null;
    }
  }


  // Static methods
  // --------------

  /**
   * @return ReadableTransformer
   */
  static function getInstance(): ReadableTransformer {
    static $transformer;
    if (!isset($transformer)) {
      $transformer = new ReadableTransformer();
    }

    return $transformer;
  }
}
