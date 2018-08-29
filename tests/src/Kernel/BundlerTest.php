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
    list(, , $messages) = $this->bundler->build();

    $bundleMapping = $this->bundler->getBundleMapping();
    self::assertEquals(3, count($bundleMapping), '3 js files built.');

    $messages = implode("\n", $messages);
    foreach ($bundleMapping as $entryPoint => $files) {
      $this->assertRegExp("/Entrypoint $entryPoint/", $messages, 'Expected entrypoint found in the messages.');
    }
  }

}
