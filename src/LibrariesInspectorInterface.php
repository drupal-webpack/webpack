<?php

namespace Drupal\webpack;

interface LibrariesInspectorInterface {

  /**
   * Returns an array of js files that should be treated with webpack.
   *
   * @return array
   */
  public function getEntryPoints();

  /**
   * Returns a library by name.
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
