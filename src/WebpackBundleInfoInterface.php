<?php

namespace Drupal\webpack;

/**
 * Facilitates storage and retrieval of webpack bundle information.
 */
interface WebpackBundleInfoInterface {

  /**
   * Returns the bundle mapping from the state or config.
   *
   * @see ::getBundleMappingStorage
   *
   * @return array|null
   */
  public function getBundleMapping();

  /**
   * Saves the bundle mapping to state or config, depending on the output dir.
   *
   * @see ::getBundleMappingStorage
   *
   * @param array $mapping
   *   The mapping between Drupal js files and the resulting webpack bundles.
   */
  public function setBundleMapping($mapping);

  /**
   * Returns the bundle mapping storage, it can be either state or config.
   *
   * The former happens when the output dir is located somewhere in the public
   * files folder. In this case it is assumed that the build happens at deploy
   * time and the mapping is saved to the State not to clutter the config space.
   *
   * When the directory is set to any other place, commit-time compilation
   * is assumed. In this case there is little chance that the server will use
   * the same database and thus the mapping needs to be persistent (saved in
   * config).
   *
   * @return string
   */
  public function getBundleMappingStorage();

  /**
   * Returns the URL under which the dev server was last started.
   *
   * @return mixed
   */
  public function getServeUrl();

}
