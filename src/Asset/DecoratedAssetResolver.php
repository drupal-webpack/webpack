<?php

namespace Drupal\webpack\Asset;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\webpack\WebpackLibrariesTrait;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class DecoratedAssetResolver implements AssetResolverInterface {

  use WebpackLibrariesTrait;

  /**
   * @var AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(AssetResolverInterface $assetResolver, LibraryDiscoveryInterface $libraryDiscovery, StateInterface $state, LoggerInterface $logger, ConfigFactoryInterface $configFactory) {
    $this->assetResolver = $assetResolver;
    $this->libraryDiscovery = $libraryDiscovery;
    $this->state = $state;
    $this->logger = $logger;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getCssAssets(AttachedAssetsInterface $assets, $optimize) {
    return $this->assetResolver->getCssAssets($assets, $optimize);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\webpack\Asset\WebpackAssetUnsupportedTypeException
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize) {
    $devMode = $this->devServerEnabled();
    $bundleMapping = $this->getBundleMapping();
    $result = $this->assetResolver->getJsAssets($assets, $optimize);

    if (!$devMode && !$bundleMapping) {
      // We're in prod mode and there's no mapping of library files. Return the
      // result of the core resolver.
      return $result;
    }

    $webpackLibs = [
      'header' => [],
      'footer' => [],
    ];

    $libraries = $this->getLibrariesToLoad($assets);
    foreach ($libraries as $key => $libraryName) {
      list($extension, $name) = explode('/', $libraryName, 2);
      $library = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (!$this->isWebpackLib($library)) {
        continue;
      }

      $scope = !empty($library['header']) ? 'header' : 'footer';

      $webpackLibs[$scope][$libraryName] = $library;
      unset($libraries[$key]);
    }
    $assets->setLibraries($libraries);

    // TODO: Make this configurable. See ::devServerEnabled.
    $serveUrl = 'http://localhost:8080';

    foreach (['header', 'footer'] as $scopeKey => $scope) {
      foreach ($webpackLibs[$scope] as $libraryId => $library) {
        foreach ($library['js'] as $jsAssetInfo) {
          if ($jsAssetInfo['type'] != 'file') {
            // Other types of assets will be ignored.
            throw new WebpackAssetUnsupportedTypeException();
          }
          $path = $jsAssetInfo['data'];
          $fileId = $this->getJsFileId($libraryId, $path);
          if ($devMode) {
            // TODO: Get the files to include from the output of webpack:serve
            //       after finding a way to tap into its output.
            $bundleName = "$fileId.bundle.js";
            $result[$scopeKey][$path] = [
              'type' => 'file',
              'group' => JS_DEFAULT,
              'weight' => 0,
              'cache' => FALSE,
              'preprocess' => FALSE,
              'attributes' => [],
              'version' => NULL,
              'browsers' => [],
              'scope' => $scope,
              'minified' => TRUE,
              'data' => "$serveUrl/$bundleName",
            ];
          } else {
            if (!isset($bundleMapping[$fileId])) {
              // Did you forget to run `drush webpack:build`?
              $this->logger->error('Missing bundle mapping for @fileId. Run `drush webpack:build` to fix.', ['@fileId' => $fileId]);
              continue;
            }
            foreach ($bundleMapping[$fileId] as $bundleFilePath) {
              if (!file_exists($bundleFilePath)) {
                // File had been built but it was removed from the filesystem
                // afterwards.
                $this->logger->error('@bundleFilePath not found. Run `drush webpack:build` to fix.', ['$@bundleFilePath' => $bundleFilePath]);
                continue;
              }
              $result[$scopeKey][$path] = [
                'type' => 'file',
                'group' => JS_DEFAULT,
                'weight' => 0,
                'cache' => FALSE,
                'preprocess' => FALSE,
                'attributes' => [],
                'version' => NULL,
                'browsers' => [],
                'scope' => $scope,
                'minified' => TRUE,
                'data' => "$bundleFilePath",
              ];
            }
          }
        }
      }
    }

    return $result;
  }

  protected function getLibrariesToLoad(AttachedAssetsInterface $assets) {
    try {
      // getLibrariesToLoad in AssetResolver is protected.
      $r = new ReflectionMethod($this->assetResolver, 'getLibrariesToLoad');
      $r->setAccessible(true);
      return $r->invoke($this->assetResolver, $assets);
    } catch (\ReflectionException $ex) {
      // This won't happen. We know that the AssetResolver has the
      // getLibrariesToLoad method. This block is here just for the IDE to stop
      // complaining.
    }
  }

  protected function devServerEnabled() {
    // TODO: Make the dev server port configurable.
    $connection = @fsockopen('localhost', '8080');

    if (is_resource($connection)) {
      fclose($connection);
      return true;
    }

    return false;
  }

}

class WebpackAssetUnsupportedTypeException extends \Exception {}

class WebpackAssetBundleMappingNotFoundException extends \Exception {}

class WebpackAssetBundleFileMissingException extends \Exception {}
