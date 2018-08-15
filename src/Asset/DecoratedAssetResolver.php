<?php

namespace Drupal\webpack\Asset;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\webpack\WebpackLibrariesTrait;
use ReflectionMethod;

class DecoratedAssetResolver implements AssetResolverInterface {

  use WebpackLibrariesTrait;

  /**
   * @var AssetResolverInterface
   */
  protected $assetResolver;

  public function __construct(AssetResolverInterface $assetResolver, LibraryDiscoveryInterface $libraryDiscovery) {
    $this->assetResolver = $assetResolver;
    $this->libraryDiscovery = $libraryDiscovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getCssAssets(AttachedAssetsInterface $assets, $optimize) {
    return $this->assetResolver->getCssAssets($assets, $optimize);
  }

  /**
   * {@inheritdoc}
   * @throws \Drupal\webpack\Asset\WebpackAssetUnsupportedTypeException
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize) {
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

    $result = $this->assetResolver->getJsAssets($assets, $optimize);

    $devMode = TRUE;
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
          $bundleName = "$fileId.bundle.js";
          if ($devMode) {
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
            // TODO: Add the production build.
          }
        }
      }
    }

    // TODO: Find a way to output vendor files from all entries in a single chunk.

    // TODO: Prepend the vendor file to the footer (or header if there were any header libs).

    // TODO: Add the processed files to corresponding parts.

    // TODO: Check for dev mode.

    return $result;
  }

  protected function getLibrariesToLoad(AttachedAssetsInterface $assets) {
    // getLibrariesToLoad in AssetResolver is protected.
    $r = new ReflectionMethod($this->assetResolver, 'getLibrariesToLoad');
    $r->setAccessible(true);
    return $r->invoke($this->assetResolver, $assets);
  }

}

class WebpackAssetUnsupportedTypeException extends \Exception {}
