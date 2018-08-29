<?php

namespace Drupal\webpack;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

class WebpackConfigBuilder implements WebpackConfigBuilderInterface {

  /**
   * @var \Drupal\webpack\LibrariesInspectorInterface
   */
  protected $librariesInspector;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * WebpackConfigBuilder constructor.
   *
   * @param \Drupal\webpack\LibrariesInspectorInterface $librariesInspector
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   */
  public function __construct(LibrariesInspectorInterface $librariesInspector, FileSystemInterface $fileSystem, ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, ModuleHandlerInterface $moduleHandler) {
    $this->librariesInspector = $librariesInspector;
    $this->fileSystem = $fileSystem;
    $this->configFactory = $configFactory;
    $this->loggerChannel = $loggerChannel;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function buildWebpackConfig($context) {
    $config = [
      'mode' => 'development',
      //      'optimization' => [
      //        'splitChunks' => [
      //          'chunks' => 'all',
      //          'cacheGroups' => [
      //            'vendors' => [
      //              'test' => '/[\\/]node_modules[\\/]/',
      //              'priority' => -10,
      //              'reuseExistingChunk' => TRUE,
      //              'name' => 'function () { return \'vendor\'; }'
      //            ],
      //          ],
      //        ],
      //      ],
      //      'module' => [
      //        'rules' => [
      //          [
      //            'test' => '/\.(js|jsx)$/',
      //            'exclude' => /node_modules/,
      //        use: ['babel-loader']
      //          ]
      //    ]
      //  },
      //  resolve: {
      //      extensions: ['*', '.js', '.jsx']
      //  },
    ];
    foreach ($this->getEntryPoints() as $id => $path) {
      $config['entry'][$id] = DRUPAL_ROOT . '/' . $path;
    }

    /** @var \Drupal\webpack\Plugin\ConfigProcessorPluginManager $configProcessorManager */
    $configProcessorManager = \Drupal::service('plugin.manager.webpack.config_processor');
    foreach ($configProcessorManager->getAllSorted() as $configProcessorPlugin) {
      $configProcessorPlugin->processConfig($config, $context);
    }

    $this->moduleHandler->alter('webpack_config', $config);

    $this->validateConfig($config);

    return $config;
  }


  /**
   * {@inheritdoc}
   */
  public function writeWebpackConfig($config) {
    $prefix = '';
    if (isset($config['#lines_before'])) {
      $prefix = implode("\n", $config['#lines_before']) . "\n";
      unset($config['#lines_before']);
    }

    // The strings provided in backticks should be unquoted.
    $entities = $this->mapJsEntities($config);
    // Encode and re-add the function bodys.
    $configString = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $this->decodeJsEntities($configString, $entities);

    $content = $prefix . "module.exports = $configString";

    $path = file_unmanaged_save_data(
      $content,
      'temporary://webpack.config.js',
      FILE_EXISTS_REPLACE);
    if ($path === FALSE) {
      throw new WebpackConfigWriteException();
    }
    return $this->fileSystem->realpath($path);
  }

  /**
   *{@inheritdoc}
   */
  public function getOutputDir($createIfNeeded = FALSE) {
    $outputDir = $this->configFactory->get('webpack.settings')->get('output_path');
    if ($createIfNeeded && !file_prepare_directory($outputDir, FILE_CREATE_DIRECTORY)) {
      $this->loggerChannel->error(
        'Webpack output directory @dir is not writable.',
        ['@dir' => $outputDir]
      );
      return false;
    }
    return $outputDir;
  }

  /**
   * Returns an array of js files that should be treated with webpack.
   *
   * @return array
   */
  protected function getEntryPoints() {
    $entryPoints = [];
    foreach ($this->librariesInspector->getAllLibraries() as $extension => $libraries) {
      foreach ($libraries as $libraryName => $library) {
        if (!$this->librariesInspector->isWebpackLib($library)) {
          continue;
        }

        foreach ($library['js'] as $jsAssetInfo) {
          $path = $jsAssetInfo['data'];
          $id = $this->librariesInspector->getJsFileId("$extension/$libraryName", $path);
          $entryPoints[$id] = $path;
        }
      }
    }

    return $entryPoints;
  }

  /**
   * Checks if the given config array is valid.
   *
   * @param array $config
   *   The full webpack config.
   *
   * @return void
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   */
  protected function validateConfig($config) {
    if (!isset($config['entry']) || empty($config['entry'])) {
      throw new WebpackConfigNotValidException('There are no files to process.');
    }
  }

  /**
   * Recursively looks for strings matching a given pattern and replaces them
   * with their hashes. Returns the map of replaced items.
   *
   * @param array $input
   *   An associative array to search for the entities in.
   *
   * @return array
   */
  protected function mapJsEntities(&$input) {
    $mapping = [];
    assert(is_array($input));
    foreach ((array)$input as $key => $value) {
      if (is_array($value) || is_object($value)) {
        $mapping = array_merge($mapping, $this->mapJsEntities($input[$key]));
      }
      if (is_string($value) && preg_match('/^`.*`$/', $value)) {
        $hash = $this->hash($value);
        $mapping["\"$hash\""] = trim($value, '`');
        $input[$key] = $hash;
      }
    }
    return $mapping;
  }

  /**
   * Replaces all occurrences of the keys of the mapping array with the
   * corresponding values.
   *
   * @param string &$string
   * @param array $mapping
   */
  protected function decodeJsEntities(&$string, $mapping) {
    $string = str_replace(array_keys($mapping), array_values($mapping), $string);
  }

  /**
   * Returns a hash of the given value.
   *
   * @param string $value
   *
   * @return string
   */
  protected function hash($value) {
    return hash('sha256', $value);
  }

}

class WebpackConfigNotValidException extends \Exception {}

class WebpackConfigWriteException extends \Exception {}
