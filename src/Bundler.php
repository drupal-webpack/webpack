<?php

namespace Drupal\webpack;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\npm\Plugin\NpmExecutableInterface;
use Drupal\npm\Plugin\NpmExecutablePluginManager;

class Bundler implements BundlerInterface {

  /**
   * @var \Drupal\webpack\WebpackConfigBuilderInterface
   */
  protected $webpackConfigBuilder;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\npm\Plugin\NpmExecutableInterface
   */
  protected $npmExecutable;

  /**
   * WebpackDrushCommands constructor.
   *
   * @param \Drupal\webpack\WebpackConfigBuilderInterface $webpackConfigBuilder
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\npm\Plugin\NpmExecutablePluginManager $npmExecutablePluginManager
   *
   * @throws \Drupal\npm\Plugin\NpmExecutableNotFoundException
   */
  public function __construct(WebpackConfigBuilderInterface $webpackConfigBuilder, StateInterface $state, ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem, NpmExecutablePluginManager $npmExecutablePluginManager) {
    $this->webpackConfigBuilder = $webpackConfigBuilder;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
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

    $this->setBundleMapping($mapping);

    $messages = ["Files written to '$outputDir':"];
    $messages = array_merge($messages, $entryPointLines);

    if ($this->getBundleMappingStorage() === 'config') {
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

  /**
   * {@inheritdoc}
   */
  public function getBundleMapping() {
    $storageType = $this->getBundleMappingStorage();
    if ($storageType === 'config') {
      return $this->configFactory->get('webpack.build_metadata')->get('bundle_mapping');
    }

    return $this->state->get('webpack_bundle_mapping');
  }

  /**
   * {@inheritdoc}
   */
  public function getServePort() {
    return $this->state->get('webpack_serve_port', NULL);
  }

  /**
   * Returns the bundle mapping storage, it can be either state or config.
   *
   * The former happens when the output dir is located somewhere in the public
   * files folder. In this case it is assumed that the build happens at deploy
   * time and the mapping is saved to the State not to clutter the config space.
   *
   * When the directory is set to any other place, commit-time compilation
   * is assumed. In this case there is little chance that the server will use
   * the same database and thus the mapping needs to be persistent (saved in
   * config).
   *
   * @return string
   */
  protected function getBundleMappingStorage() {
    if (strpos($this->webpackConfigBuilder->getOutputDir(), 'public://') !== 0) {
      // TODO: Some other schemes might be valid too.
      return 'config';
    } else {
      return 'state';
    }
  }

  /**
   * Saves the bundle mapping to state or config, depending on the output dir.
   *
   * @see ::getBundleMappingStorage
   *
   * @param array $mapping
   *   The mapping between Drupal js files and the resulting webpack bundles.
   */
  protected function setBundleMapping($mapping) {
    $storageType = $this->getBundleMappingStorage();
    if ($storageType === 'config') {
      $this->configFactory
        ->getEditable('webpack.build_metadata')
        ->set('bundle_mapping', $mapping)
        ->save();
    } elseif ($storageType === 'state') {
      $this->state->set('webpack_bundle_mapping', $mapping);
    }
  }

}
