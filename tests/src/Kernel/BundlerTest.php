<?php

namespace Drupal\Tests\webpack\Kernel;

class BundlerTest extends WebpackTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  public function testBuild() {
    self::assertNull($this->bundler->getBundleMapping(), 'Bundle mapping is empty initially.');
    $this->bundler->build();
    $mapping = $this->bundler->getBundleMapping();
    self::assertEquals(3, count($mapping), '3 js files built.');
  }

}
