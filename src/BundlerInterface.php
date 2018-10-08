<?php

namespace Drupal\webpack;

interface BundlerInterface {

  /**
   * Builds webpack libraries.
   *
   * @return array
   *   An array with two items: 0: success and 1: output of the command
   *
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   * @throws \Drupal\webpack\WebpackConfigWriteException
   * @throws \Drupal\npm\Exception\NpmCommandFailedException
   */
  public function build();

  /**
   * Starts the dev server.
   *
   * This method never returns, it can only throw exceptions. $customListener
   * can be used to parse the output.
   *
   * @param int $port
   *   Local port to start the dev server on. Defaults to 1234.
   * @param null|callable $customListener
   *   Callback that will be passed to \Symfony\Process\Process::wait().
   * @param int|NULL $timeout
   *   Timeout in seconds.
   *
   * @throws \Drupal\webpack\WebpackConfigNotValidException
   * @throws \Drupal\webpack\WebpackConfigWriteException
   * @throws \Drupal\npm\Exception\NpmCommandFailedException
   * @throws \RuntimeException
   */
  public function serve($port = 1234, $customListener = NULL, $timeout = NULL);

}
