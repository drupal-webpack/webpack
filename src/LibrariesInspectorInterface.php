<?php

namespace Drupal\webpack;

interface LibrariesInspectorInterface {

  /**
   * Returns all the libraries defined by enabled modules and themes.
   *
   * @return array
   */
  public function getAllLibraries();

  /**
   * Return a library by name.
   *
   * @param string $extension
   * @param string $name
   *
   * @return array|false
   */
  public function getLibraryByName($extension, $name);

  /**
   * Checks if given library should be handled by webpack.
   *
   * @param array $library
   *   The associative array representing the library.
   *
   * @return bool
   */
  public function isWebpackLib($library);

  /**
   * Returns a unique id for a js file comprising the module, lib, and file name.
   *
   * @param string $libraryId
   * @param string $filepath
   *
   * @return string
   */
  public function getJsFileId($libraryId, $filepath);

}
