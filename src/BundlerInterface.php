<?php

namespace Drupal\webpack;

interface BundlerInterface {

  /**
   * Starts the dev server.
   *
   * This method never returns, it can only throw exceptions. $customListener
   * can be used to parse the output.
   *
   * @param int $port
   *   Local port to start the dev server on. Defaults to 1234.
   * @param bool $docker
   *   If true, set make the command work inside docker. Set the host to 0.0.0.0
   *   and disable host checking.
   * @param string $devServerHost
   *   The name of the host to look for the dev server at. Defaults to
   *   localhost. In docker-compose-driven projects it's the name of the service
   *   in which the dev server is started.
   * @param null|callable $customListener
   *   Callback that will be passed to \Symfony\Process\Process::wait().
   * @param int|NULL $timeout
   *   Timeout in seconds.
   *
   * @return
   */
  public function serve($port = 1234, $docker = false, $devServerHost = 'localhost', $customListener = NULL, $timeout = NULL);

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

}
