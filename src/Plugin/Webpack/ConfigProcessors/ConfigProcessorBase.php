<?php

namespace Drupal\webpack\Plugin\Webpack\ConfigProcessors;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\webpack\BundlerInterface;
use Drupal\webpack\Exception\WebpackException;
use Drupal\webpack\Plugin\ConfigProcessorPluginInterface;
use Drupal\webpack\WebpackConfigBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Config Processors.
 */
abstract class ConfigProcessorBase extends PluginBase implements ConfigProcessorPluginInterface, ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\webpack\BundlerInterface
   */
  protected $bundler;

  /**
   * @var \Drupal\webpack\WebpackConfigBuilderInterface
   */
  protected $webpackConfigBuilder;

  /**
   * ConfigProcessorBase constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\webpack\BundlerInterface $bundler
   * @param \Drupal\webpack\WebpackConfigBuilderInterface $webpackConfigBuilder
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileSystemInterface $fileSystem, BundlerInterface $bundler, WebpackConfigBuilderInterface $webpackConfigBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $fileSystem;
    $this->bundler = $bundler;
    $this->webpackConfigBuilder = $webpackConfigBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('webpack.bundler'),
      $container->get('webpack.config_builder')
    );
  }

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

class WebpackConfigNodeModulesNotFoundException extends WebpackException {

}
