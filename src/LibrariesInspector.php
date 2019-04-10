<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\webpack\Exception\WebpackException;

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
  public function getAllEntryPoints() {
    $entryPoints = [];
    foreach ($this->getAllWebpackLibraries() as $extension => $libraries) {
      foreach ($libraries as $libraryName => $library) {
        $entryPoints += $this->getEntryPoints($library, "$extension/$libraryName");
      }
    }

    return $entryPoints;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntryPoints($library, $libraryId) {
    $entryPoints = [];
    foreach ($library['js'] as $jsAssetInfo) {
      $path = isset($jsAssetInfo['source']) ? $jsAssetInfo['source'] : $jsAssetInfo['data'];
      $id = $this->getJsFileId($libraryId, $path);
      $entryPoints[$id] = $path;
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
  public function getLibraryById($libraryId) {
    $library = $extension = NULL;
    $libParts = explode('/', $libraryId);
    if (count($libParts) === 2) {
      list($extensionName, $libName) = $libParts;

      // The extension can be either a theme or a module.
      try {
        $extension = $this->moduleHandler->getModule($extensionName);
      } catch (UnknownExtensionException $e) {
        $extension = $this->themeHandler->getTheme($extensionName);
      }

      $library = $this->getLibraryByName($extensionName, $libName);
    }

    if (!$library || !$extension) {
      // Library not found. Probably something's wrong with the name.
      throw new WebpackLibraryIdNotValidException();
    }

    return $this->processLibrary($library, $extension);
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
   * {@inheritdoc}
   */
  public function getAllWebpackLibraries() {
    $result = [];
    foreach ($this->getAllLibraries() as $extension => $libraries) {
      foreach ($libraries as $libraryName => $library) {
        if ($this->isWebpackLib($library)) {
          $result[$extension][$libraryName] = $library;
        }
      }
    }
    return $result;
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
      $extensionLibraries = $this->libraryDiscovery->getLibrariesByExtension($extension->getName());

      foreach ($extensionLibraries as &$library) {
        $library = $this->processLibrary($library, $extension);
      }

      $libraries[$extension->getName()] = $extensionLibraries;
    }

    return $libraries;
  }

  /**
   * Processes the library definition for the build process.
   *
   * @param array $library
   *   The library definition.
   * @param \Drupal\Core\Extension\Extension $extension
   *   The parent extension (module or theme).
   *
   * @return mixed
   */
  protected function processLibrary($library, Extension $extension) {
    foreach ($library['js'] as &$fileDescription) {
      if (isset($fileDescription['source']) && !empty($fileDescription['source'])) {
        $fileDescription['source'] = $extension->getPath() . '/' . $fileDescription['source'];
      }
    }
    return $library;
  }

}

class WebpackLibraryIdNotValidException extends WebpackException {

  public function __construct($message = "", $code = 0, \Throwable $previous = NULL) {
    if (empty($message)) {
      $message = 'The provided library id is not valid or the library doesn\'t exist. Expected format: [module_name]/[library_name].';
    }
    parent::__construct($message, $code, $previous);
  }

}
