<?php

namespace Drupal\webpack\Plugin;

interface ConfigProcessorPluginInterface {

  /**
   * Performs config processing.
   *
   * The core module sets 'entry' and 'output' objects. Plugins can add new
   * fields as well as change the existing ones. Processors are executed in
   * the order determined by the weight property, starting with the lowest.
   *
   * @param array &$config
   *   The webpack config array. All child elements are required to be either
   *   scalars or associative arrays. Functions, regular expressions and any
   *   other values that need to be evaluated in javascript need to be enclosed
   *   with ``.
   * @param array $context
   *   Context in which the config is built. Keys:
   *   - command: 'build' or 'serve'. The drush command that triggered this.
   *
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   */
  public function processConfig(&$config, $context);

}
