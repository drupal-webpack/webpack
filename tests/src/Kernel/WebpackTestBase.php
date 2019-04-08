<?php

namespace Drupal\Tests\webpack\Kernel;

use Drupal\KernelTests\KernelTestBase;

abstract class WebpackTestBase extends KernelTestBase {

  protected static $modules = ['npm', 'system', 'webpack', 'webpack_test_libs'];

  /**
   * @var \Drupal\npm\Plugin\NpmExecutableInterface
   */
  protected $npmExecutable;

  /**
   * @var \Drupal\webpack\LibrariesInspectorInterface
   */
  protected $librariesInspector;

  /**
   * @var \Drupal\webpack\WebpackConfigBuilderInterface
   */
  protected $webpackConfigBuilder;

  /**
   * @var \Drupal\webpack\WebpackBundleInfoInterface
   */
  protected $webpackBundleInfo;

  /**
   * @var \Drupal\webpack\BundlerInterface
   */
  protected $bundler;

  /**
   * {@inheritdoc}
   * @throws \Drupal\npm\Exception\NpmCommandFailedException
   * @throws \Drupal\npm\Plugin\NpmExecutableNotFoundException
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->npmExecutable = $this->container->get('plugin.manager.npm_executable')->getExecutable();
    $this->librariesInspector = $this->container->get('webpack.libraries_inspector');
    $this->webpackConfigBuilder= $this->container->get('webpack.config_builder');
    $this->webpackBundleInfo = $this->container->get('webpack.bundle_info');
    $this->bundler = $this->container->get('webpack.bundler');

    $this->installConfig('webpack');
    $this->installConfig('webpack_test_libs');
    $this->installConfig('system');

    // Run the install hook manually. It'll set some webpack config.
    \module_load_include('install', 'webpack_test_libs');
    \webpack_test_libs_install();

    $this->npmExecutable->initPackageJson();

    // Add webpack dependencies.
    $this->npmExecutable->addPackages(['webpack', 'webpack-cli', 'webpack-dev-server']);

    // And the test libs.
    $this->npmExecutable->addPackages(['ramda']);
  }

}
