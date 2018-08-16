<?php

namespace Drupal\webpack\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation for Webpack config processor plugins.
 *
 * @Annotation
 */
class WebpackConfigProcessor extends Plugin {

  /**
   * Weight for precedence calculations.
   *
   * @var int
   */
  public $weight = 0;

}
