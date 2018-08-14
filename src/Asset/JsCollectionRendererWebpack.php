<?php

namespace Drupal\webpack\Asset;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\JsCollectionRenderer;
use Drupal\Core\State\StateInterface;

class JsCollectionRendererWebpack implements AssetCollectionRendererInterface {

  /**
   * The original core renderer.
   *
   * @var \Drupal\Core\Asset\JsCollectionRenderer
   */
  protected $jsCollectionRender;

  /**
   * Service constructor.
   *
   * @param \Drupal\Core\Asset\JsCollectionRenderer $jsCollectionRender
   *   The original core renderer.
   */
  public function __construct(JsCollectionRenderer $jsCollectionRender) {
    $this->jsCollectionRender = $jsCollectionRender;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $assets) {
    static $i = 0;
    $scripts = $this->jsCollectionRender->render($assets);
    $toMerge = [];

    foreach ($scripts as $key => $script) {
      if (!isset($script['#attributes']['src'])) {
        continue;
      }

      $src = $script['#attributes']['src'];

      // TODO: Do all that at the Asset Resolver level.
      // TODO: Swap the asset resolver service.
      // TODO: Grab libraries eligible for webpacking by a field in the lib definition.
      if (substr($src, 0, 15) == '/modules/custom') {
        // TODO: Check if es6 file exists.
        $es6 = str_replace('.js', '.es6.js', $src);
        $toMerge[] = "./web$es6";
        unset($scripts[$key]);
      }
    }

    if (empty($toMerge)) {
      return $scripts;
    }

    $files = escapeshellcmd(implode(' ', $toMerge));

    // TODO: Take this from config.
    $outputDir = './web/sites/default/files/';
    $outputFile = "bundle.{$i}.js";
    $i++;

    // TODO: Change the backtick operator to something that returns an exit code (exec?).
    $result = `yarn webpack $files --output-path='$outputDir' --output-filename='$outputFile'`;

    // TODO: Handle failures.

    $scripts[] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => '',
      '#attributes' => ['src' => "/sites/default/files/$outputFile"],
    ];

    return $scripts;
  }

}

/*

Decorate the AssetResolver service.

For js files, take the assets from the header and from the footer, look for the
"webpack" group and run these through webpack.

The libraries need to be in the webpack group (check feasibility) and have minified: true.

// DONE: Write a drush command that will serve the bundle consisting of all webpack libraries defined in enabled modules / themes.

// DONE: Build a dynamic webpack config file with CommonChunksPlugin to leverage long term vendor caching.

// TODO: Decorate the asset resolver service.

// TODO: In the asset resolver, check if the dev server is available and add its external file if so.

// TODO: Build the entry file names

// TODO: Write a drush command to build all the webpack libraries.

// TODO: Add separation for vendor and each lib.

// TODO: Add the ability to configure webpack config additions (alter hook).

// TODO: Add the ability to override the executable (yarn).

// TODO: Add an npm package with all the dependencies and, possibly, scripts.

// TODO: Make the webpack-serve port configurable.

// TODO: Write a test module with an example webpack library.

// TODO: Write a test for getEntryPoints.

// TODO: Write a test for getWebpackConfig.

// TODO: Write a test for writeWebpackConfig.

 */
