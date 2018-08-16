<?php

namespace Drupal\webpack;

trait WebpackLibrariesTrait {

  /**
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  protected function getAllLibraries() {
    $libraries = [];
    $extensions = array_merge(
      $this->moduleHandler->getModuleList(),
      $this->themeHandler->listInfo()
    );

    /** @var \Drupal\Core\Extension\Extension $extension */
    foreach ($extensions as $extension) {
      $libraries[$extension->getName()] =
        $this->libraryDiscovery->getLibrariesByExtension($extension->getName());
    }

    return $libraries;
  }

  protected function isWebpackLib($library) {
    return isset($library['webpack']) && $library['webpack'];
  }

  protected function getJsFileId($libraryId, $filepath) {
    $filename = basename($filepath, '.js');
    list($extension, $libraryName) = explode('/', $libraryId);
    return "$extension-$libraryName-$filename";
  }

  protected function setBundleMapping($mapping) {
    $this->state->set('webpack_bundle_mapping', $mapping);
  }

  protected function getBundleMapping() {
    return $this->state->get('webpack_bundle_mapping');
  }

}
