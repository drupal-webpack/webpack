<?php

namespace Drupal\webpack;

interface BundlerInterface {

  /**
   * Builds webpack libraries.
   *
   * @return array
   *   An array with two items: 0: success and 1: output of the command
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   * @throws \Drupal\webpack\WebpackConfigWriteException
   */
  public function build();

  /**
   * Start the dev server.
   *
   * @param $port
   *
   * @return mixed
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   * @throws \Drupal\webpack\WebpackConfigWriteException
   */
  public function serve($port = '8080');

  /**
   * Returns the bundle mapping from the state or config.
   *
   * @see ::getBundleMappingStorage
   *
   * @return array|null
   */
  public function getBundleMapping();

  /**
   * Returns the port on which the dev server was last started
   *
   * @return mixed
   */
  public function getServePort();

}
