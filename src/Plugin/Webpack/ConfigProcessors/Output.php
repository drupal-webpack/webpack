<?php

namespace Drupal\webpack\Plugin\Webpack\ConfigProcessors;

use Drupal\webpack\Annotation\WebpackConfigProcessor;
use Drupal\webpack\WebpackConfigNotValidException;

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
    $outputDirPrepared = $this->webpackConfigBuilder->getOutputDir(TRUE);
    if (!$outputDirPrepared) {
      throw new WebpackConfigNotValidException('Output directory is not writable.');
    }
    $config['output'] = [
      'filename' => '[name].bundle.js',
      'path' => $this->fileSystem->realpath($outputDirPrepared),
    ];
  }

}
