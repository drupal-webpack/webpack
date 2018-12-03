<?php

namespace Drupal\webpack;

interface WebpackConfigBuilderInterface {

  /**
   * Builds webpack config.
   *
   * @param array $context
   *   The build context. Possible keys:
   *   - 'command' => the command that triggered the generation ['serve' | 'build'].
   *   - 'library' => only present when command == 'build-single'. The target library definition.
   * @param string $outputDir
   *   An output directory.
   * @param string $outputNamePattern
   *   A pattern to use for output files.
   * @param String[]|null $entrypoints
   *   An array of entrypoints. Defaults to all available webpack JS files.
   *
   * @return array
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   */
  public function buildWebpackConfig($context, $outputDir, $outputNamePattern = '[name].bundle.js', $entrypoints = NULL);

  /**
   * Writes the webpack config to a temp dir.
   *
   * @param array $config
   *   The config to write.
   *
   * @return false|string
   * @throws \Drupal\webpack\WebpackConfigWriteException
   */
  public function writeWebpackConfig($config);

  /**
   * Returns the path to the build output directory.
   *
   * @return string
   */
  public function getOutputDir();

  /**
   * Returns path to an output file for given library.
   *
   * @param array $library
   *   Target library's definition.
   *
   * @return mixed
   * @throws \Drupal\webpack\WebpackNotAWebpackLibraryException
   * @throws \Drupal\webpack\WebpackSingleLibraryInvalidNumberOfJsEntrypointsException
   */
  public function getSingleLibOutputFilePath($library);

}
