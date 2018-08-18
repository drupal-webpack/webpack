<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\State\StateInterface;

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

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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

  /**
   * Returns true if the given library should be handled by webpack.
   *
   * @param $library
   *
   * @return bool
   */
  protected function isWebpackLib($library) {
    return isset($library['webpack']) && $library['webpack'];
  }

  /**
   * Returns a unique id for a js file comprising the module, lib and file names.
   *
   * @param string $libraryId
   * @param string $filepath
   *
   * @return string
   */
  protected function getJsFileId($libraryId, $filepath) {
    $filename = basename($filepath, '.js');
    list($extension, $libraryName) = explode('/', $libraryId);
    return "$extension-$libraryName-$filename";
  }

  /**
   * Returns the path to the build output directory.
   *
   * @param bool $createIfNeeded
   *
   * @return string
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  protected function getOutputDir($createIfNeeded = FALSE) {
    $outputDir = $this->configFactory->get('webpack.settings')->get('output_path');
    if ($createIfNeeded && !file_prepare_directory($outputDir, FILE_CREATE_DIRECTORY)) {
      throw new WebpackDrushOutputDirNotWritableException();
    }
    return $outputDir;
  }

  /**
   * Returns the bundle mapping storage, it can be either state or config.
   *
   * The former happens when the output dir is located somewhere in the public
   * files folder. In this case it is assumed that the build happens at deploy
   * time and the mapping is saved to the State not to clutter the config space.
   *
   * When the directory is set to any other place, commit-time compilation
   * is assumed. In this case there is little chance that the server will use
   * the same database and thus the mapping needs to be persistent (saved in
   * config).
   *
   * @return string
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  protected function getBundleMappingStorage() {
    if (strpos($this->getOutputDir(), 'public://') !== 0) {
      // TODO: Some other schemes might be valid too.
      return 'config';
    } else {
      return 'state';
    }
  }

  /**
   * Saves the bundle mapping to state or config, depending on the output dir.
   *
   * @see ::getBundleMappingStorage
   *
   * @param array $mapping
   *   The mapping between Drupal js files and the resulting webpack bundles.
   *
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  protected function setBundleMapping($mapping) {
    $storageType = $this->getBundleMappingStorage();
    if ($storageType === 'config') {
      $this->configFactory
        ->getEditable('webpack.build_info')
        ->set('mapping', $mapping)
        ->save();
    } elseif ($storageType === 'state') {
      $this->state->set('webpack_bundle_mapping', $mapping);
    }
  }

  /**
   * Returns the bundle mapping from the state or config.
   *
   * @see ::getBundleMappingStorage
   *
   * @return array|null
   * @throws \Drupal\webpack\WebpackDrushOutputDirNotWritableException
   */
  protected function getBundleMapping() {
    $storageType = $this->getBundleMappingStorage();
    if ($storageType === 'config') {
      return $this->configFactory->get('webpack.build_info')->get('mapping');
    }

    return $this->state->get('webpack_bundle_mapping');
  }

}
