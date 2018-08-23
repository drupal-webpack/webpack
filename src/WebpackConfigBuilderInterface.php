<?php

namespace Drupal\webpack;

interface WebpackConfigBuilderInterface {

  /**
   * Returns the fully-built webpack config.
   *
   * @param array $context
   *   The build context. Possible keys:
   *   - 'command' => the command that triggered the generation ['serve' | 'build'].
   *
   * @return array
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   */
  public function buildWebpackConfig($context);

  /**
   * Writes the webpack config to a temp dir.
   *
   * @param array $config
   *   The config to write.
   *
   * @return false|string
   * @throws \Drupal\webpack\WebpackDrushConfigWriteException
   */
  public function writeWebpackConfig($config);

  /**
   * Returns the path to the build output directory.
   *
   * @param bool $createIfNeeded
   *
   * @return string
   */
  public function getOutputDir($createIfNeeded = FALSE);

}
