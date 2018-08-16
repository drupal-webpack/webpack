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
   * @param array $config
   *   The webpack config array. All child elements are required to be either
   *   scalars or associative arrays. Functions can be added as strings, they
   *   need to start with the 'function' keyword though.
   */
  public function processConfig(&$config);

}
