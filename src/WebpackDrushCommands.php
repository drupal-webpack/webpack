<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\DrushCommands;

/**
 * Webpack's Drush commands.
 */
class WebpackDrushCommands extends DrushCommands {

  use WebpackLibrariesTrait;

  protected $fileSystem;

  public function __construct(ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, LibraryDiscoveryInterface $libraryDiscovery, FileSystemInterface $fileSystem) {
    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->libraryDiscovery = $libraryDiscovery;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Serves the webpack dev bundle.
   *
   * @command webpack:serve
   * @aliases wpsrv
   * @usage drush wepback:serve
   *   Serve the js files.
   *
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   * @throws \Drupal\webpack\WebpackDrushConfigWriteException
   */
  public function serve($options = []) {
    $this->output()->writeln('Hey!');

    $outputDir = 'public://webpack';
    if (!file_prepare_directory($outputDir, FILE_CREATE_DIRECTORY)) {
      throw new WebpackDrushOutputDirNotWritableException();
    }

    $entryPoints = $this->getEntryPoints();
    $config = $this->getWebpackConfig($entryPoints, $outputDir);
    $path = $this->fileSystem->realpath($this->writeWebpackConfig($config));

    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack-serve $path";
    $this->output()->writeln($cmd);
    system($cmd);
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

  protected function getWebpackConfig($entryPoints, $outputDir) {
    $config = [
      'mode' => 'development',
      'output' => [
        'filename' => '[name].bundle.js',
        'path' => $this->fileSystem->realpath($outputDir),
      ],
// Fuck it, it's dev.
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
    foreach ($entryPoints as $id => $path) {
      $config['entry'][$id] = DRUPAL_ROOT . '/' . $path;
    }
    return $config;
  }

  protected function writeWebpackConfig($config) {
    // Functions don't work after json_encode.
    $functions = $this->mapJsFunctions($config);
    // Encode and re-add the function bodys.
    $configString = $this->decodeJsFunctions(json_encode($config, JSON_PRETTY_PRINT), $functions);
    $content = "module.exports = $configString";
    $path = file_unmanaged_save_data(
      $content,
      'temporary://webpack.config.js',
      FILE_EXISTS_REPLACE);
    if ($path === FALSE) {
      throw new WebpackDrushConfigWriteException();
    }
    return $path;
  }

  protected function mapJsFunctions(&$input) {
    $functions = [];
    assert(is_array($input) || is_object($input));
    foreach ((array)$input as $key => $value) {
      if (is_array($value) || is_object($value)) {
        $functions = array_merge($functions, $this->mapJsFunctions($input[$key]));
      }
      if (is_string($value) && strpos($value, 'function') === 0) {
        $hash = hash('sha256', $value);
        $functions["\"$hash\""] = $value;
        $input[$key] = $hash;
      }
    }
    return $functions;
  }

  protected function decodeJsFunctions($string, $functions) {
    return str_replace(array_keys($functions), array_values($functions), $string);
  }

}

class WebpackDrushOutputDirNotWritableException extends \Exception {}

class WebpackDrushConfigWriteException extends \Exception {}

/*

Decorate the AssetResolver service.

For js files, take the assets from the header and from the footer, look for the
"webpack" group and run these through webpack.

The libraries need to be in the webpack group (check feasibility) and have minified: true.

// DONE: Write a drush command that will serve the bundle consisting of all webpack libraries defined in enabled modules / themes.

// DONE: Build a dynamic webpack config file with CommonChunksPlugin to leverage long term vendor caching.

// TODO: Decorate the asset resolver service.

// TODO: Implement hook_js_alter and remove all files handled by webpack from $js.

// TODO: In the asset resolver, check if the dev server is available and add its external file if so.

// TODO: Build the entry file names

// TODO: Write a drush command to build all the webpack libraries.

// TODO: Implement hook_requirements that checks if the required npm packages are installed.

// TODO: Don't do any processing when the npm packages aren't there.

// TODO: Check if the build exists before unsetting a lib from standard processing.

// TODO: Add separation for vendor and each lib.

// TODO: Add the ability to configure webpack config additions (alter hook).

// TODO: Add caching in the decorated resolver.

// TODO: Add the ability to override the executable (yarn).

// TODO: Make the webpack-serve port configurable.

// TODO: Write a test module with an example webpack library.

// TODO: Write a test for getEntryPoints.

// TODO: Write a test for getWebpackConfig.

// TODO: Write a test for writeWebpackConfig.

// TODO: Write an install drush command that will install the dependencies via yarn or npm.

// TODO: Build a plugin system for enhancing the webpack config.

// TODO: Instead of checking the dev mode, check if a process with drush webpack:serve is running.

// TODO: Add webpack_babel.

// TODO: Add webpack_vue.

// TODO: Make it possible to use arrow functions in mapJsFunctions (regexp).

 */
