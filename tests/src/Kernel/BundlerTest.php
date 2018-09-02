<?php

namespace Drupal\Tests\webpack\Kernel;

use Drupal\npm\Exception\NpmCommandFailedException;
use Drupal\webpack\WebpackConfigNotValidException;
use Drupal\webpack\WebpackConfigWriteException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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

  public function testServe() {
    system('pkill node');
    $bundler = $this->bundler;
    $counter = 0;

    $this->assertNull($bundler->getServePort(), 'Serve port is null initially.');

    $outputListener = function ($type, $buffer) use ($bundler, &$counter) {
      if ($counter++ == 2) {
        // In the third chunk of output we expect the port to be set and
        // the bundles to be reachable.
        $port = $bundler->getServePort();
        $this->assertNotEmpty($bundler->getServePort(), 'Serve port has been saved.');

        $client = \Drupal::httpClient();
        foreach ($this->librariesInspector->getEntryPoints() as $id => $path) {
          $response = $client->get("http://localhost:$port/$id.bundle.js");
          $this->assertEquals(200, $response->getStatusCode(), "Bundle $id is reachable on the dev server.");
        }
      }
    };

    try {
      $this->bundler->serve(1234, $outputListener, 15);
    } catch (ProcessTimedOutException $e) {
      // The process is expected to time out.
    }
  }

}
