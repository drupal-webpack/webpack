<?php

namespace Drupal\Tests\webpack\Kernel;

use Drupal\npm\Exception\NpmCommandFailedException;

class BundlerTest extends WebpackTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  public function testBuild() {
    self::assertEmpty($this->bundler->getBundleMapping(), 'Bundle mapping is empty initially.');
    try {
      $this->bundler->build();
    } catch (NpmCommandFailedException $e) {
      throw new \Exception($e->getProcess()->getOutput());
    }
    $mapping = $this->bundler->getBundleMapping();
    self::assertEquals(3, count($mapping), '3 js files built.');
  }

}
