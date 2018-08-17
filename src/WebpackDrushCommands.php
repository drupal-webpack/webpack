<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
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

  protected $fileSystem;

  public function __construct(ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, LibraryDiscoveryInterface $libraryDiscovery, StateInterface $state, FileSystemInterface $fileSystem) {
    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->libraryDiscovery = $libraryDiscovery;
    $this->state = $state;
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

    $configPath = $this->writeWebpackConfig();

    $output = [];
    $exitCode = NULL;
    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack --config $configPath";
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0) {
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
      $this->output()->writeln('No libraries were written.');
    }

    $this->setBundleMapping($mapping);

    $this->output()->writeln('Build successful.');
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
   */
  public function serve($options = []) {
    $this->output()->writeln('Hey!');

    $configPath = $this->writeWebpackConfig();

    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack-serve $configPath";
    system($cmd);
  }

  protected function getOutputDir($createIfNeeded = FALSE) {
    // TODO: Make the output dir configurable.
    $outputDir = 'public://webpack';
    if ($createIfNeeded && !file_prepare_directory($outputDir, FILE_CREATE_DIRECTORY)) {
      throw new WebpackDrushOutputDirNotWritableException();
    }
    return $outputDir;
  }

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

  protected function getWebpackConfig() {
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
      $configProcessorPlugin->processConfig($config);
    }

    $this->moduleHandler->alter('webpack_config', $config);

    return $config;
  }

  /**
   * @return false|string
   * @throws \Drupal\webpack\WebpackDrushConfigWriteException
   */
  protected function writeWebpackConfig() {
    $config = $this->getWebpackConfig();
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

  protected function hash($value) {
    return hash('sha256', $value);
  }

  protected function decodeJsEntities(&$string, $mapping) {
    $string = str_replace(array_keys($mapping), array_values($mapping), $string);
  }

}

class WebpackDrushConfigWriteException extends \Exception {}

class WebpackDrushOutputDirNotWritableException extends \Exception {}

class WebpackDrushBuildFailedException extends \Exception {}

/*

Decorate the AssetResolver service.

For js files, take the assets from the header and from the footer, look for the
"webpack" group and run these through webpack.

The libraries need to be in the webpack group (check feasibility) and have minified: true.

// DONE: Write a drush command that will serve the bundle consisting of all webpack libraries defined in enabled modules / themes.

// DONE: Build a dynamic webpack config file with CommonChunksPlugin to leverage long term vendor caching.

// DONE: Decorate the asset resolver service.

// DONE: In the asset resolver, check if the dev server is available and add its external file if so.

// DONE: Write a drush command to build all the webpack libraries.

// DONE: Check if the build exists before unsetting a lib from standard processing.

// DONE: Add the ability to configure webpack config additions (plugin system).

// DONE: Add the ability to configure webpack config additions (alter hook).

// DONE: Add webpack_babel.

// DONE: Publish the presentation and link it in the modules's description under How does it work.

// TODO: Add a second $context param to processConfig.

// TODO: Add webpack_vue.

// TODO: Update the presentation to show examples of webpack, webpack_babel and webpack_vuejs.

// TODO: Add a link to the presentation in all the readmes.

// TODO: Add example usage to webpack's README.

// TODO: Add example usage to webpack_babel's README.

// TODO: Add example usage to webpack_vuejs' README.

// TODO: Add whitespace to function and regexp regexps :).

// TODO: Add separation for vendor and each lib.

// TODO: Implement hook_requirements that checks if the required npm packages are installed.

// TODO: Don't do any processing when the npm packages aren't there.

// TODO: Add webpack_react.

// TODO: Move config building to a dedicated service.

// TODO: Add caching in the decorated resolver.

// TODO: Add the ability to override the executable (yarn).

// TODO: Move the entry building to a dedicated plugin.

// TODO: Make the webpack-serve port configurable.

// TODO: Write an integration testing infrastructure based on drupal-dev.io

// TODO: Write a test module with an example webpack library.

// TODO: Write a test for getEntryPoints.

// TODO: Write a test for getWebpackConfig.

// TODO: Write a test for writeWebpackConfig.

// TODO: Write an install drush command that will install the dependencies via yarn or npm.

// TODO: Build a plugin system for enhancing the webpack config.

// TODO: Instead of checking the dev mode, check if a process with drush webpack:serve is running.

// TODO: Find a way to tap into webpack-serve output to get the generated bundles.

// TODO: Find a way to output vendor files from all entries in a single chunk.

// TODO: Make it possible to use arrow functions in mapJsFunctions (regexp).

// TODO: Add documentation.

// TODO: Include the names of the built files in the output of webpack:build.

// TODO: Add meaningful messages to exceptions.

 */
