<?php

namespace Drupal\webpack;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Facilitates storage and retrieval of webpack bundle information.
 */
class WebpackBundleInfo implements WebpackBundleInfoInterface {

  /**
   * @var \Drupal\webpack\WebpackConfigBuilderInterface
   */
  protected $webpackConfigBuilder;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * WebpackBundleInfo constructor.
   *
   * @param \Drupal\webpack\WebpackConfigBuilderInterface $webpackConfigBuilder
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(WebpackConfigBuilderInterface $webpackConfigBuilder, StateInterface $state, ConfigFactoryInterface $configFactory) {
    $this->webpackConfigBuilder = $webpackConfigBuilder;
    $this->state = $state;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleMapping() {
    $storageType = $this->getBundleMappingStorage();
    if ($storageType === 'config') {
      return $this->configFactory->get('webpack.build_metadata')->get('bundle_mapping');
    }

    return $this->state->get('webpack_bundle_mapping');
  }

  /**
   * {@inheritdoc}
   */
  public function setBundleMapping($mapping) {
    $storageType = $this->getBundleMappingStorage();
    if ($storageType === 'config') {
      $this->configFactory
        ->getEditable('webpack.build_metadata')
        ->set('bundle_mapping', $mapping)
        ->save();
    } elseif ($storageType === 'state') {
      $this->state->set('webpack_bundle_mapping', $mapping);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleMappingStorage() {
    if (strpos($this->webpackConfigBuilder->getOutputDir(), 'public://') !== 0) {
      // TODO: Some other schemes might be valid too.
      return 'config';
    } else {
      return 'state';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getServeUrl() {
    return $this->state->get('webpack_serve_url', NULL);
  }

}
