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
    $reachableLibraries = [];

    $this->assertNull($bundler->getServePort(), 'Serve port is null initially.');

    $outputListener = function ($type, $buffer) use ($bundler, &$reachableLibraries) {
      $port = $bundler->getServePort();
      if ($port) {
        $client = \Drupal::httpClient();
        foreach ($this->librariesInspector->getEntryPoints() as $id => $path) {
          $response = $client->get("http://localhost:$port/$id.bundle.js");
          if ($response->getStatusCode() == 200) {
            $reachableLibraries[$id] = TRUE;
          }
        }
      }
    };

    try {
      $this->bundler->serve(1234, $outputListener, 5);
    } catch (ProcessTimedOutException $e) {
      // The process is expected to time out.
    } finally {
      $this->assertEquals(3, count($reachableLibraries), "Serve port is set and all libs are reachable on the dev server.");
    }
  }

}
