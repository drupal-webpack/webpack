<?php

namespace Drupal\webpack\Asset;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\State\StateInterface;
use Drupal\webpack\LibrariesInspectorInterface;
use Drupal\webpack\WebpackBundleInfoInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class DecoratedAssetResolver implements AssetResolverInterface {

  /**
   * @var AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * @var \Drupal\webpack\LibrariesInspectorInterface
   */
  protected $librariesInspector;

  /**
   * @var \Drupal\webpack\WebpackBundleInfoInterface
   */
  protected $webpackBundleInfo;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * DecoratedAssetResolver constructor.
   *
   * @param \Drupal\Core\Asset\AssetResolverInterface $assetResolver
   * @param \Drupal\webpack\LibrariesInspectorInterface $librariesInspector
   * @param \Drupal\webpack\WebpackBundleInfoInterface $webpackBundleInfo
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(AssetResolverInterface $assetResolver, LibrariesInspectorInterface $librariesInspector, WebpackBundleInfoInterface $webpackBundleInfo, StateInterface $state, LoggerInterface $logger) {
    $this->assetResolver = $assetResolver;
    $this->librariesInspector = $librariesInspector;
    $this->webpackBundleInfo = $webpackBundleInfo;
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getCssAssets(AttachedAssetsInterface $assets, $optimize) {
    return $this->assetResolver->getCssAssets($assets, $optimize);
  }

  /**
   * {@inheritdoc}
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize) {
    $devUrl = $this->getDevServerUrl();
    $devMapping = $this->state->get('webpack_serve_mapping', []);
    $bundleMapping = $this->webpackBundleInfo->getBundleMapping();
    $result = $this->assetResolver->getJsAssets($assets, $optimize);

    if (!$devUrl && !$bundleMapping) {
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
      $library = $this->librariesInspector->getLibraryByName($extension, $name);
      if (!$this->librariesInspector->isWebpackLib($library)) {
        continue;
      }

      $scope = !empty($library['header']) ? 'header' : 'footer';

      $webpackLibs[$scope][$libraryName] = $library;
      unset($libraries[$key]);
    }
    $assets->setLibraries($libraries);

    foreach (['header', 'footer'] as $scopeKey => $scope) {
      foreach ($webpackLibs[$scope] as $libraryId => $library) {
        foreach ($library['js'] as $jsAssetInfo) {
          if ($jsAssetInfo['type'] != 'file') {
            // Other types of assets will be ignored.
            $this->logger->warning(
              'Only file assets are supported. Got @type.',
              ['@type' => $jsAssetInfo['type']]
            );
          }
          $path = $jsAssetInfo['data'];
          if (!isset($result[$scopeKey][$path])) {
            continue;
          }
          $fileId = $this->librariesInspector->getJsFileId($libraryId, $path);
          $defaults = $result[$scopeKey][$path] + [
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
          ];
          if ($devUrl) {
            $suffix = '';
            foreach ($devMapping[$fileId] as $fileName) {
              $result[$scopeKey]["$path$suffix"] = [
                'cache' => FALSE,
                'preprocess' => FALSE,
                'minified' => TRUE,
                'type' => 'file',
                'data' => "$devUrl/$fileName",
              ] + $defaults;
              $suffix = empty($suffix) ? 1 : $suffix + 1;
            }
          } else {
            if (!isset($bundleMapping[$fileId])) {
              // Did you forget to run `drush webpack:build`?
              $this->logger->error(
                'Missing bundle mapping for @fileId. Run `drush webpack:build` to fix.',
                ['@fileId' => $fileId]
              );
              continue;
            }
            $suffix = '';
            foreach ($bundleMapping[$fileId] as $bundleFilePath) {
              if (!file_exists($bundleFilePath)) {
                // File had been built but it was removed from the filesystem
                // afterwards.
                $this->logger->error(
                  '@bundleFilePath not found. Run `drush webpack:build` to fix.',
                  ['$@bundleFilePath' => $bundleFilePath]
                );
                continue;
              }
              $result[$scopeKey]["$path$suffix"] = [
                  'preprocess' => FALSE,
                  'minified' => TRUE,
                  'type' => 'file',
                  'data' => $bundleFilePath,
                ] + $defaults;
              $suffix = empty($suffix) ? 1 : (int)$suffix + 1;
            }
          }
        }
      }
    }

    return $result;
  }

  /**
   * Returns the result of AssetResolver::getLibrariesToLoad (it's protected).
   *
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
   *
   * @return array
   */
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

  /**
   * Returns true when the dev server is enabled.
   *
   * @return bool
   */
  protected function getDevServerUrl() {
    $url = $this->webpackBundleInfo->getServeUrl();
    if (!$url) {
      return false;
    }
    list($host, $port) = explode(':', $url);
    $connection = @fsockopen($host, $port);

    if (is_resource($connection)) {
      fclose($connection);
      // The host is only needed to check the dev server availability in this
      // method. We're on the server now. The browser won't be able to reach the
      // docker hostname of the dev server machine (if one was provided), so
      // hardcode the host as 'localhost'. The developer needs to take care
      // of binding the port from the container to the host.
      return "http://localhost:$port";
    }

    return false;
  }

}
