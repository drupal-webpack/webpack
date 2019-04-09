<?php

namespace Drupal\webpack;

interface LibrariesInspectorInterface {

  /**
   * Returns an array of all js files that should be treated with webpack.
   *
   * @return array
   */
  public function getAllEntryPoints();

  /**
   * Return a list of entry points for a given library.
   *
   * @param array $library
   *   The library definition.
   * @param string $libraryId
   *   The library's id in this format [extension_name]/[library_name].
   *
   * @return array
   */
  public function getEntryPoints($library, $libraryId);

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
   * Returns a library definition based on id with additional processing
   * and validation.
   *
   * @param string $libraryId
   *   The library's id in this format [extension_name]/[library_name].
   *
   * @return mixed
   * @throws \Drupal\webpack\WebpackLibraryIdNotValidException
   */
  public function getLibraryById($libraryId);

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
   *   The library's id in this format [extension_name]/[library_name].
   * @param string $filepath
   *   Path to the file, relative to the extension's root.
   *
   * @return string
   */
  public function getJsFileId($libraryId, $filepath);

  /**
   * Returns all the webpack libraries grouped by module/theme name.
   *
   * @return array
   */
  public function getAllWebpackLibraries();

}
