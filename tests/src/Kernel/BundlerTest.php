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
    self::assertEmpty($this->webpackBundleInfo->getBundleMapping(), 'Bundle mapping is empty initially.');
    list(, , $messages) = $this->bundler->build();

    $bundleMapping = $this->webpackBundleInfo->getBundleMapping();
    self::assertEquals(3, count($bundleMapping), '3 js files built.');

    $messages = implode("\n", $messages);
    foreach ($bundleMapping as $entryPoint => $files) {
      $this->assertRegExp("/Entrypoint $entryPoint/", $messages, 'Expected entrypoint found in the messages.');
    }
  }

  public function testServe() {
    system('pkill node');
    $webpackBundleInfo = $this->webpackBundleInfo;
    $reachableLibraries = [];

    $this->assertNull($webpackBundleInfo->getServeUrl(), 'Serve url is null initially.');

    $outputListener = function ($type, $buffer) use ($webpackBundleInfo, &$reachableLibraries) {
      $url = $webpackBundleInfo->getServeUrl();
      if ($url) {
        $client = \Drupal::httpClient();
        foreach ($this->librariesInspector->getEntryPoints() as $id => $path) {
          $response = $client->get("http://$url/$id.bundle.js");
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
