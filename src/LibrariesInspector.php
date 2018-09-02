<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;

class LibrariesInspector implements LibrariesInspectorInterface {

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
   * WebpackDrushCommands constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, ThemeHandlerInterface $themeHandler, LibraryDiscoveryInterface $libraryDiscovery) {
    $this->moduleHandler = $moduleHandler;
    $this->themeHandler = $themeHandler;
    $this->libraryDiscovery = $libraryDiscovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntryPoints() {
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
   * {@inheritdoc}
   */
  public function getLibraryByName($extension, $name) {
    return $this->libraryDiscovery->getLibraryByName($extension, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function isWebpackLib($library) {
    return isset($library['webpack']) && $library['webpack'];
  }

  /**
   * {@inheritdoc}
   */
  public function getJsFileId($libraryId, $filepath) {
    $filename = basename($filepath, '.js');
    list($extension, $libraryName) = explode('/', $libraryId);
    return "$extension-$libraryName-$filename";
  }

  /**
   * Returns all the libraries defined by enabled modules and themes.
   *
   * @return array
   */
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

}
