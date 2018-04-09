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
