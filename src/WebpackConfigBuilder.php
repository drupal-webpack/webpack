<?php

namespace Drupal\webpack;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\webpack\Exception\WebpackException;
use Drupal\webpack\Plugin\ConfigProcessorPluginManager;

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
   * @var \Drupal\webpack\Plugin\ConfigProcessorPluginManager
   */
  protected $configProcessorPluginManager;

  /**
   * WebpackConfigBuilder constructor.
   *
   * @param \Drupal\webpack\LibrariesInspectorInterface $librariesInspector
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   */
  public function __construct(LibrariesInspectorInterface $librariesInspector, FileSystemInterface $fileSystem, ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, ModuleHandlerInterface $moduleHandler, ConfigProcessorPluginManager $configProcessorPluginManager) {
    $this->librariesInspector = $librariesInspector;
    $this->fileSystem = $fileSystem;
    $this->configFactory = $configFactory;
    $this->loggerChannel = $loggerChannel;
    $this->moduleHandler = $moduleHandler;
    $this->configProcessorPluginManager = $configProcessorPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildWebpackConfig($context, $outputDir, $outputNamePattern = '[name].bundle.js', $entrypoints = NULL) {
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
    ];
    if (empty($entrypoints)) {
      $entrypoints = $this->librariesInspector->getAllEntryPoints();
    }
    foreach ($entrypoints as $id => $path) {
      $config['entry'][$id] = DRUPAL_ROOT . '/' . $path;
    }

    if (!$this->prepareDirectory($outputDir)) {
      throw new WebpackConfigNotValidException('Output directory is not writable.');
    }

    $config['output'] = [
      'filename' => $outputNamePattern,
      'path' => $this->fileSystem->realpath($outputDir),
    ];

    foreach ($this->configProcessorPluginManager->getAllSorted() as $configProcessorPlugin) {
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
    // Encode and re-add the function bodies.
    $configString = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $this->decodeJsEntities($configString, $entities);

    $content = $prefix . "module.exports = $configString";

    $path = $this->saveData($content, 'temporary://webpack.config.js');
    if ($path === FALSE) {
      throw new WebpackConfigWriteException();
    }
    return $this->fileSystem->realpath($path);
  }

  /**
   *{@inheritdoc}
   */
  public function getOutputDir() {
    return $this->configFactory->get('webpack.settings')->get('output_path');
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleLibOutputFilePath($library) {
    if (!$this->librariesInspector->isWebpackLib($library)) {
      // Library found but it's not a webpack lib.
      throw new WebpackNotAWebpackLibraryException();
    }

    if (count($library['js']) !== 1) {
      // For the libraries to work without the webpack module they need to have
      // exactly one JS entrypoint.
      throw new WebpackSingleLibraryInvalidNumberOfJsEntrypointsException();
    }

    return $library['js'][0]['data'];
  }

  /**
   * Checks if the given directory exists and creates it if needed.
   *
   * @param string $outputDir
   *   The target directory path.
   *
   * @return bool
   *   True if the directory is writable.
   */
  protected function prepareDirectory($outputDir) {
    if (method_exists($this->fileSystem, 'prepareDirectory')) {
      $dirPrepared = $this->fileSystem->prepareDirectory($outputDir, FILE_CREATE_DIRECTORY);
    } else {
      // TODO: Remove when Drupal 8.6 is EOL.
      $dirPrepared = file_prepare_directory($outputDir, FILE_CREATE_DIRECTORY);
    }

    if (!$dirPrepared) {
      $this->loggerChannel->error(
        'Webpack output directory @dir is not writable.',
        ['@dir' => $outputDir]
      );
      return FALSE;
    }
    return TRUE;
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

  /**
   * Saved the given content in a temp location
   *
   * @param string $content
   *   Contents of the file to save.
   * @param $destination
   *   The target uri.
   *
   * @return bool|string
   */
  protected function saveData($content, $destination) {
    if (method_exists($this->fileSystem, 'saveData')) {
      return $this->fileSystem->saveData(
        $content,
        $destination,
        FILE_EXISTS_REPLACE);
    } else {
      // TODO: Remove when drupal 8.6 is EOL.
      return file_unmanaged_save_data(
        $content,
        $destination,
        FILE_EXISTS_REPLACE);
    }
  }

}

class WebpackConfigNotValidException extends WebpackException {

  public function __construct(string $message = "", int $code = 0, \Throwable $previous = NULL) {
    if (empty($message)) {
      $message = 'The provided webpack config is not valid.';
    }
    parent::__construct($message, $code, $previous);
  }

}

class WebpackConfigWriteException extends WebpackException {

  public function __construct(string $message = "", int $code = 0, \Throwable $previous = NULL) {
    if (empty($message)) {
      $message = 'Webpack config couldn\'t be written.';
    }
    parent::__construct($message, $code, $previous);
  }

}

class WebpackNotAWebpackLibraryException extends WebpackException {

  public function __construct(string $message = "", int $code = 0, \Throwable $previous = NULL) {
    if (empty($message)) {
      $message = 'The provided library is not marked to be handled with webpack. Add "webpack: true" to its definition.';
    }
    parent::__construct($message, $code, $previous);
  }

}

class WebpackSingleLibraryInvalidNumberOfJsEntrypointsException extends WebpackException {

  public function __construct(string $message = "", int $code = 0, \Throwable $previous = NULL) {
    if (empty($message)) {
      $message = 'Only libraries with exactly one JavaScript entry file can be built as stand-alone.';
    }
    parent::__construct($message, $code, $previous);
  }

}
