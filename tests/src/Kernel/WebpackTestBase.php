<?php

namespace Drupal\Tests\webpack\Kernel;

use Drupal\KernelTests\KernelTestBase;

abstract class WebpackTestBase extends KernelTestBase {

  protected static $modules = [ 'system', 'webpack', 'webpack_test_libs' ];

  /**
   * @var \Drupal\webpack\LibrariesInspectorInterface
   */
  protected $librariesInspector;

  /**
   * @var \Drupal\webpack\WebpackConfigBuilderInterface
   */
  protected $webpackConfigBuilder;

  /**
   * @var \Drupal\webpack\BundlerInterface
   */
  protected $bundler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->librariesInspector = $this->container->get('webpack.libraries_inspector');
    $this->webpackConfigBuilder= $this->container->get('webpack.config_builder');
    $this->bundler = $this->container->get('webpack.bundler');

    $this->installConfig('system');
  }

}
