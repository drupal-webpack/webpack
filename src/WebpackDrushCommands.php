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

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  protected $libraryDiscovery;

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
   * @throws \Drupal\webpack\WebpackOutputDirNotWritableException
   * @throws \Drupal\webpack\WebpackConfigWriteException
   */
  public function serve($options = []) {
    $this->output()->writeln('Hey!');

    $outputDir = 'public://webpack';
    if (!file_prepare_directory($outputDir, FILE_CREATE_DIRECTORY)) {
      throw new WebpackOutputDirNotWritableException();
    }

    $entryPoints = $this->getEntryPoints();
    $config = $this->getWebpackConfig($entryPoints, $outputDir);
    $path = $this->fileSystem->realpath($this->writeWebpackConfig($config));

    // DONE: Install webpack serve.

    // TODO: Run webpack serve.
    $cmd = "yarn --cwd=" . DRUPAL_ROOT . " webpack-serve $path";
    $this->output()->writeln($cmd);
    system($cmd);
  }

  protected function getEntryPoints() {
    $entryPoints = [];
    foreach ($this->getAllLibraries() as $library) {
      if (!isset($library['js'])) {
        continue;
      }

      foreach ($library['js'] as $jsAssetInfo) {
        if (isset($jsAssetInfo['webpack']) && $jsAssetInfo['webpack']) {
          $entryPoints[] = DRUPAL_ROOT . '/' . $jsAssetInfo['data'];
        }
      }
    }
    return $entryPoints;
  }

  protected function getAllLibraries() {
    $libraries = [];
    $extensions = array_merge(
      $this->moduleHandler->getModuleList(),
      $this->themeHandler->listInfo()
    );

    /** @var \Drupal\Core\Extension\Extension $extension */
    foreach ($extensions as $extension) {
      $extensionLibs = $this->libraryDiscovery->getLibrariesByExtension($extension->getName());
      $libraries = array_merge($libraries, $extensionLibs);
    }

    return $libraries;
  }

  protected function getWebpackConfig($entryPoints, $outputDir) {
    $config = [
      'mode' => 'development',
      'output' => [
        'filename' => '[name].bundle.js',
        'path' => $this->fileSystem->realpath($outputDir),
      ],
      'optimization' => [
        'splitChunks' => [
          'chunks' => 'all',
        ],
      ],
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
    foreach ($entryPoints as $path) {
      $name = basename($path, '.js');
      $config['entry'][$name] = $path;
    }
    return $config;
  }

  protected function writeWebpackConfig($config) {
    $content = 'module.exports = ' . json_encode($config, JSON_PRETTY_PRINT);
    $path = file_unmanaged_save_data(
      $content,
      'temporary://webpack.config.js',
      FILE_EXISTS_REPLACE);
    if ($path === FALSE) {
      throw new WebpackConfigWriteException();
    }
    return $path;
  }

}

class WebpackOutputDirNotWritableException extends \Exception {}

class WebpackConfigWriteException extends \Exception {}
