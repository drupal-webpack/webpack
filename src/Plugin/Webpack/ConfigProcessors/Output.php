<?php

namespace Drupal\webpack\Plugin\Webpack\ConfigProcessors;

use Drupal\webpack\Annotation\WebpackConfigProcessor;

/**
 * Add the
 *
 * @WebpackConfigProcessor(
 *   id = "output",
 *   weight = -2,
 * )
 */
class Output extends ConfigProcessorBase {

  /**
   * {@inheritdoc}
   */
  public function processConfig(&$config, $context) {
    // TODO: Move output building here from WebpackDrushCommands.
  }

}
