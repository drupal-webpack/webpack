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
   * @var \Drupal\npm\Plugin\NpmExecutablePluginManager
   */
  protected $npmExecutablePluginManager;

  /**
   * @var \Drupal\webpack\LibrariesInspectorInterface
   */
  protected $librariesInspector;

  /**
   * Bundler constructor.
   *
   * @param \Drupal\webpack\WebpackConfigBuilderInterface $webpackConfigBuilder
   * @param \Drupal\webpack\WebpackBundleInfoInterface $webpackBundleInfo
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\npm\Plugin\NpmExecutablePluginManager $npmExecutablePluginManager
   */
  public function __construct(WebpackConfigBuilderInterface $webpackConfigBuilder, WebpackBundleInfoInterface $webpackBundleInfo, StateInterface $state, ConfigFactoryInterface $configFactory, NpmExecutablePluginManager $npmExecutablePluginManager, LibrariesInspectorInterface $librariesInspector) {
    $this->webpackConfigBuilder = $webpackConfigBuilder;
    $this->webpackBundleInfo = $webpackBundleInfo;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $this->npmExecutablePluginManager = $npmExecutablePluginManager;
    $this->librariesInspector = $librariesInspector;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $mapping = [];
    $outputDir = $this->webpackConfigBuilder->getOutputDir();
    $config = $this->webpackConfigBuilder->buildWebpackConfig(['command' => 'build'], $outputDir);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);

    $process = $this->getNpmExecutable()->runScript(['webpack', '--config', $configPath]);

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
  public function buildSingle($libraryId) {
    $library = $this->librariesInspector->getLibraryById($libraryId);

    $outputPath = $this->webpackConfigBuilder->getSingleLibOutputFilePath($library);
    $outputPathParts = explode('/', $outputPath);
    $outputFileName = array_pop($outputPathParts);
    $outputDir = implode('/', $outputPathParts);

    $context = [
      'command' => 'build-single',
      'library' => $library,
    ];
    $entryPoints = $this->librariesInspector->getEntryPoints($library, $libraryId);
    $config = $this->webpackConfigBuilder->buildWebpackConfig($context, $outputDir, $outputFileName, $entryPoints);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);
    $process = $this->getNpmExecutable()->runScript(['webpack', '--config', $configPath]);

    return [TRUE, $process, []];
  }

  /**
   * {@inheritdoc}
   */
  public function serve($port = '1234', $docker = false, $devServerHost = 'localhost', $customListener = NULL, $timeout = NULL) {
    $outputDir = $this->webpackConfigBuilder->getOutputDir();
    $config = $this->webpackConfigBuilder->buildWebpackConfig(['command' => 'serve'], $outputDir);
    $configPath = $this->webpackConfigBuilder->writeWebpackConfig($config);

    // Webpack-dev-server will pick a free port if the given one is occupied, so
    // we need to parse the output to get the final value.
    $args = ['webpack-dev-server', '--config', $configPath, '--port', $port];
    if ($docker) {
      $args = array_merge($args, ['--host', '0.0.0.0', '--disable-host-check']);
    }
    $state = $this->state;

    // Reset the file mapping.
    $state->set('webpack_serve_mapping', []);
    $outputListener = function ($type, $buffer) use ($state, $customListener, $devServerHost) {
      $matches = [];
      $pattern = '/Project is running at .*\:([0-9]+)/';
      if (preg_match($pattern, $buffer, $matches)) {
        $port = $matches[1];
        $state->set('webpack_serve_url', "$devServerHost:$port");
      }

      if (preg_match_all('/Entrypoint (.*) = (.*)/', $buffer, $matches)) {
        list($lines, $entryPoints, $files) = $matches;
        $devMapping = $state->get('webpack_serve_mapping', []);
        foreach ($entryPoints as $key => $entryPoint) {
          $entryPoint = $this->clearOutput($entryPoint);
          foreach (explode(' ', $files[$key]) as $fileName) {
            $devMapping[$entryPoint][] = $this->clearOutput($fileName);
          }
        }
        $state->set('webpack_serve_mapping', $devMapping);
      }

      if (is_callable($customListener)) {
        call_user_func($customListener, $type, $buffer);
      }
    };
    $this->getNpmExecutable()->runScript($args, $outputListener, $timeout);
  }

  /**
   * {@inheritdoc}
   */
  public function getNpmExecutable() {
    return $this->npmExecutablePluginManager->getExecutable();
  }

  /**
   * Removes bash control characters from the string.
   *
   * @param string $string
   *   Raw string
   *
   * @return string
   */
  protected function clearOutput($string) {
    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $string);
  }

}

// DC Seattle 2019 list

// DONE: Fix travis tests.

// DONE: Add testing for 8.7 and remove deprecated method calls.

// DONE: Write a patch to devel with loading dependencies.

// DONE: Update webpack's installation instructions.

// TODO: Update webpack babel's installation instructions.

// TODO: Update webpack react's installation instructions.

// TODO: Update webpack vue's installation instructions.

// TODO: Make the Vue module work.

// TODO: Add the optimizations code.
