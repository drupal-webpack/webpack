<?php

namespace Drupal\webpack;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\npm\Plugin\NpmExecutablePluginManager;

class Bundler implements BundlerInterface {

  /**
   * @var \Drupal\webpack\WebpackConfigBuilderInterface
   */
  protected $webpackConfigBuilder;

  /**
   * @var \Drupal\webpack\$webpackBundleInfo
   */
  protected $webpackBundleInfo;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\npm\Plugin\NpmExecutableInterface
   */
  protected $npmExecutable;

  /**
   * Bundler constructor.
   *
   * @param \Drupal\webpack\WebpackConfigBuilderInterface $webpackConfigBuilder
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\npm\Plugin\NpmExecutablePluginManager $npmExecutablePluginManager
   *
   * @throws \Drupal\npm\Plugin\NpmExecutableNotFoundException
   */
  public function __construct(WebpackConfigBuilderInterface $webpackConfigBuilder, WebpackBundleInfoInterface $webpackBundleInfo, StateInterface $state, ConfigFactoryInterface $configFactory, NpmExecutablePluginManager $npmExecutablePluginManager) {
    $this->webpackConfigBuilder = $webpackConfigBuilder;
    $this->webpackBundleInfo = $webpackBundleInfo;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $this->npmExecutable = $npmExecutablePluginManager->getExecutable();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $mapping = [];
    $exitCode = NULL;
    $outputDir = $this->webpackConfigBuilder->getOutputDir();
    $config = $this->webpackConfigBuilder->buildWebpackConfig(['command' => 'build']);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);

    $process = $this->npmExecutable->runScript(['webpack', '--config', $configPath]);

    $entryPointLines = [];
    if (preg_match_all('/Entrypoint (.*) = (.*)/', $process->getOutput(), $matches)) {
      list($lines, $entryPoints, $files) = $matches;
      foreach ($entryPoints as $key => $entryPoint) {
        $entryPointLines[] = $lines[$key];
        foreach (explode(' ', $files[$key]) as $fileName) {
          $mapping[$entryPoint][] = "$outputDir/$fileName";
        }
      }
    }

    if (empty($mapping)) {
      return [FALSE, $process, ['No files were written']];
    }

    $this->webpackBundleInfo->setBundleMapping($mapping);

    $messages = ["Files written to '$outputDir':"];
    $messages = array_merge($messages, $entryPointLines);

    if ($this->webpackBundleInfo->getBundleMappingStorage() === 'config') {
      $messages[] = '';
      $messages[] = "WARNING: The output directory is outside of the public files directory. The config needs to be exported in order for the files to be loaded on other environments.";
    }

    return [TRUE, $process, $messages];
  }

  /**
   * {@inheritdoc}
   */
  public function serve($port = '1234', $customListener = NULL, $timeout = NULL) {
    $config = $this->webpackConfigBuilder->buildWebpackConfig(['command' => 'serve']);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);

    // Webpack-serve will pick a free port if the given one is occupied, so
    // we need to parse the output to get the final value.
    $args = ['webpack-serve', $configPath, '--port', $port];
    $state = $this->state;
    $outputListener = function ($type, $buffer) use ($state, $customListener) {
      $matches = [];
      $pattern = '/Project is running at .*:([0-9]+)/';
      if (preg_match($pattern, $buffer, $matches)) {
        $state->set('webpack_serve_port', $matches[1]);
      }

      if (is_callable($customListener)) {
        call_user_func($customListener, $type, $buffer);
      }
    };
    $this->npmExecutable->runScript($args, $outputListener, $timeout);
  }

}
