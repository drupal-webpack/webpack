<?php

namespace Drupal\webpack\Plugin;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class ConfigProcessorPluginManager extends DefaultPluginManager {

  /**
   * Static cache of plugin instances.
   *
   * @var \Drupal\webpack\Plugin\ConfigProcessorPluginInterface[]
   */
  protected $instances;

  /**
   * ConfigProcessorPluginManager constructor.
   *
   * @param bool|string $pluginSubdirectory
   *   The plugin's subdirectory.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param string|null $pluginInterface
   *   The interface each plugin should implement.
   * @param string $pluginAnnotationName
   *   The name of the annotation that contains the plugin definition.
   */
  public function __construct(
    $pluginSubdirectory,
    \Traversable $namespaces,
    ModuleHandlerInterface $moduleHandler,
    $pluginInterface,
    $pluginAnnotationName
  ) {
    parent::__construct(
      $pluginSubdirectory,
      $namespaces,
      $moduleHandler,
      $pluginInterface,
      $pluginAnnotationName
    );

    $this->alterInfo('webpack_config_processors');
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    if (!isset($this->instances[$options['id']])) {
      $this->instances[$options['id']] = $this->createInstance($options['id']);
    }

    return $this->instances[$options['id']];
  }

  /**
   * Loads all plugins and returns them sorted by weight.
   *
   * @return \Drupal\webpack\Plugin\ConfigProcessorPluginInterface[]
   */
  public function getAllSorted() {
    $plugins = [];
    $definitions = $this->getDefinitions();
    uasort($definitions, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    foreach ($definitions as $definition) {
      $plugins[$definition['id']] = $this->getInstance($definition);
    }
    return $plugins;
  }

}
