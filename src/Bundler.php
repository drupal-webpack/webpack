<?php

namespace Drupal\webpack;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;

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
   * WebpackDrushCommands constructor.
   *
   * @param \Drupal\webpack\WebpackConfigBuilderInterface $webpackConfigBuilder
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   */
  public function __construct(WebpackConfigBuilderInterface $webpackConfigBuilder, StateInterface $state, ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem) {
    $this->webpackConfigBuilder = $webpackConfigBuilder;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $output = [];
    $mapping = [];
    $exitCode = NULL;
    $outputDir = $this->webpackConfigBuilder->getOutputDir();
    $config = $this->webpackConfigBuilder->buildWebpackConfig(['command' => 'build']);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);

    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack --config $configPath";
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0) {
      return [FALSE, $output, []];
    }

    foreach ($output as $line) {
      $matches = [];
      if (preg_match('/^Entrypoint (.*) = (.*)/', $line, $matches)) {
        list(, $entryPoint, $files) = $matches;
        foreach (explode(' ', $files) as $fileName) {
          $mapping[$entryPoint][] = "$outputDir/$fileName";
        }
      }
    }

    if (empty($mapping)) {
      return [FALSE, $output, ['No files were written']];
    }

    $this->setBundleMapping($mapping);

    $messages = ["Files written to '$outputDir':"];
    foreach ($output as $line) {
      if (strpos($line, 'Entrypoint ') === 0) {
        $messages[] = $line;
      }
    }

    if ($this->getBundleMappingStorage() === 'config') {
      $messages[] = '';
      $messages[] = "WARNING: The output directory is outside of the public files directory. The config needs to be exported in order for the files to be loaded on other environments.";
    }

    return [TRUE, $output, $messages];
  }

  /**
   * {@inheritdoc}
   */
  public function serve($port = '8080') {
    $config = $this->webpackConfigBuilder->buildWebpackConfig(['command' => 'serve']);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);

    // TODO: Get the real port from the command's output. If the port is occupied, a random one is assigned.
    $this->state->set('webpack_serve_port', $port);

    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack-serve $configPath --port $port";
    system($cmd);
  }

  /**
   *{@inheritdoc}
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
