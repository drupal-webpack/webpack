<?php

namespace Drupal\Tests\webpack\Functional;

use Drupal\Tests\BrowserTestBase;

class LoadTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['webpack'];

  /**
   * Tests that the website is operational with just the webpack module enabled.
   */
  public function testInstall() {
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
  }

}
