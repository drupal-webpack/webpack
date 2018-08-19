<?php

namespace Drupal\webpack\Plugin\Webpack\ConfigProcessors;

use Drupal\webpack\Plugin\ConfigProcessorPluginInterface;

/**
 * Base class for Config Processors.
 */
abstract class ConfigProcessorBase implements ConfigProcessorPluginInterface {

  /**
   * Returns the path to node_modules folder.
   *
   * @return string
   * @throws \Drupal\webpack\Plugin\Webpack\ConfigProcessors\WebpackConfigNodeModulesNotFoundException
   */
  protected function getPathToNodeModules() {
    $dir = DRUPAL_ROOT;
    while (!is_dir("$dir/node_modules")) {
      $parts = explode('/', $dir);
      array_pop($parts);
      if (empty(array_filter($parts))) {
        throw new WebpackConfigNodeModulesNotFoundException('Couldn\'t find node_modules nowhere up the directory tree.');
      }
      $dir = implode('/', $parts);
    }

    return "$dir/node_modules";
  }

}

class WebpackConfigNodeModulesNotFoundException extends \Exception { }
