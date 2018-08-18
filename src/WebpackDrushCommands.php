<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;

/**
 * Webpack's Drush commands.
 */
class WebpackDrushCommands extends DrushCommands {

  use WebpackLibrariesTrait;

  /**
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * WebpackDrushCommands constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, LibraryDiscoveryInterface $libraryDiscovery, StateInterface $state, ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem) {
    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->libraryDiscovery = $libraryDiscovery;
    $this->state = $state;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Builds the output files.
   *
   * @command webpack:build
   * @aliases wpbuild
   * @usage drush wepback:build
   *   Build the js bundles.
   *
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   * @throws \Drupal\webpack\WebpackDrushConfigWriteException
   * @throws \Drupal\webpack\WebpackDrushBuildFailedException
   */
  public function build($options = []) {
    $this->output()->writeln('Hey! Building the libs for you.');

    $config = $this->getWebpackConfig(['command' => 'build']);

    if (!$this->validateConfig($config)){
      // Config is invalid. Bail out.
      return;
    }

    $configPath = $this->writeWebpackConfig($config);

    $output = [];
    $exitCode = NULL;
    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack --config $configPath";
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0) {
      foreach ($output as $line) {
        $this->output()->writeln($line);
      }
      throw new WebpackDrushBuildFailedException();
    }

    $mapping = [];
    $outputDir = $this->getOutputDir();
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
      $this->output()->writeln('No files were written.');
      $this->setBundleMapping(NULL);
      return;
    }

    $this->setBundleMapping($mapping);
    $this->output()->writeln("Build successful. Files written to '$outputDir':");
    foreach ($output as $line) {
      if (strpos($line, 'Entrypoint ') === 0) {
        $this->output()->writeln($line);
      }
    }

    if ($this->getBundleMappingStorage() === 'config') {
      $this->output()->writeln('');
      $this->output()->writeln("WARNING: The output directory is outside of the public files directory. The config needs to be exported in order for the files to be loaded on other environments.");
    }
  }

  /**
   * Serves the webpack dev bundle.
   *
   * @command webpack:serve
   * @aliases wpsrv
   * @usage drush wepback:serve
   *   Serve the js files.
   *
   * @throws \Drupal\webpack\WebpackDrushConfigWriteException
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  public function serve($options = []) {
    $this->output()->writeln('Hey!');

    $configPath = $this->writeWebpackConfig($this->getWebpackConfig(['command' => 'serve']));

    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack-serve $configPath";
    system($cmd);
  }

  /**
   * Returns an array of js files that should be treated with webpack.
   *
   * @return array
   */
  protected function getEntryPoints() {
    $entryPoints = [];
    foreach ($this->getAllLibraries() as $extension => $libraries) {
      foreach ($libraries as $libraryName => $library) {
        if (!$this->isWebpackLib($library)) {
          continue;
        }

        foreach ($library['js'] as $jsAssetInfo) {
          $path = $jsAssetInfo['data'];
          $id = $this->getJsFileId("$extension/$libraryName", $path);
          $entryPoints[$id] = $path;
        }
      }
    }

    return $entryPoints;
  }

  /**
   * Returns the fully-built webpack config.
   *
   * @return array
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  protected function getWebpackConfig($context) {
    // TODO: Move this method to a separate service.
    $config = [
      'mode' => 'development',
      'output' => [
        'filename' => '[name].bundle.js',
        'path' => $this->fileSystem->realpath($this->getOutputDir(TRUE)),
      ],
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

    return $config;
  }

  /**
   * Checks if the given config array is valid.
   *
   * @param array $config
   *   The full webpack config.
   *
   * @return bool
   */
  protected function validateConfig($config) {
    if (!isset($config['entry']) || empty($config['entry'])) {
      $this->output()->writeln('There are no files to process.');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Writes the webpack config to a temp dir.
   *
   * @param array $config
   *   The config to write.
   *
   * @return false|string
   * @throws \Drupal\webpack\WebpackDrushConfigWriteException
   */
  protected function writeWebpackConfig($config) {
    // Functions don't work after json_encode.
    $functions = $this->mapJsEntities($config, '/^(function|() =>|.*=>).*/');
    // Neither do regular expression literals. We're looking for regular
    // expressions with a regular expression, so use @ as a pattern delimiter :)
    $regexps = $this->mapJsEntities($config, '@^/.*/(a-z)*$@');
    // Encode and re-add the function bodys.
    $configString = json_encode($config, JSON_PRETTY_PRINT);
    $this->decodeJsEntities($configString, $functions);
    $this->decodeJsEntities($configString, $regexps);

    $content = "module.exports = $configString";
    $path = file_unmanaged_save_data(
      $content,
      'temporary://webpack.config.js',
      FILE_EXISTS_REPLACE);
    if ($path === FALSE) {
      throw new WebpackDrushConfigWriteException();
    }
    return $this->fileSystem->realpath($path);
  }

  /**
   * Recursively looks for strings matching a given pattern and replaces them
   * with their hashes. Returns the map of replaced items.
   *
   * @param array $input
   * @param string $pattern
   *
   * @return array
   */
  protected function mapJsEntities(&$input, $pattern) {
    $mapping = [];
    assert(is_array($input));
    foreach ((array)$input as $key => $value) {
      if (is_array($value) || is_object($value)) {
        $mapping = array_merge($mapping, $this->mapJsEntities($input[$key], $pattern));
      }
      if (is_string($value) && preg_match($pattern, $value)) {
        $hash = $this->hash($value);
        $mapping["\"$hash\""] = $value;
        $input[$key] = $hash;
      }
    }
    return $mapping;
  }

  /**
   * Returns a hash of the given value..
   *
   * @param string $value
   *
   * @return string
   */
  protected function hash($value) {
    return hash('sha256', $value);
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

}

class WebpackDrushConfigWriteException extends \Exception {}

class WebpackDrushOutputDirNotWritableException extends \Exception {}

class WebpackDrushBuildFailedException extends \Exception {}

/*

// TODO: Add example usage to webpack_babel's README.

// TODO: Add example usage to webpack_vuejs' README.

 */
