<?php

namespace Drupal\webpack;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\npm\Exception\NpmCommandFailedException;
use Drush\Commands\DrushCommands;

/**
 * Webpack's Drush commands.
 */
class WebpackDrushCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * @var \Drupal\webpack\BundlerInterface
   */
  protected $bundler;

  /**
   * WebpackDrushCommands constructor.
   *
   * @param \Drupal\webpack\BundlerInterface $bundler
   */
  public function __construct(BundlerInterface $bundler) {
    $this->bundler = $bundler;
  }

  /**
   * Builds the output files.
   *
   * @command webpack:build
   * @aliases wpbuild
   * @usage drush wepback:build
   *   Build the js bundles.
   */
  public function build($options = []) {
    $result = FALSE;
    $writeLine = function ($line) {
      $this->output()->writeln($line);
    };

    $this->output()->writeln('Hey! Building the libs for you.');
    try {
      list($success, $output, $messages) = $this->bundler->build();
      array_walk($output, $writeLine);
      $this->output()->writeln('');
      array_walk($messages, $writeLine);
      $result = $success;
    } catch (WebpackConfigNotValidException $e) {
      $this->output()->writeln($e->getMessage());
    } catch (WebpackConfigWriteException $e) {
      $this->output()->writeln($e->getMessage());
    } catch (NpmCommandFailedException $e) {
      $this->output()->writeln("The npm script has failed. Details:\n{$e->getMessage()}");
    } finally {
      $this->output()->writeln($this->t(
        'Build :status',
        [':status' => $result ?
          $this->t('successful') :
          $this->t('failed')]
        )->render()
      );
    }
  }

  /**
   * Serves the webpack dev bundle.
   *
   * @command webpack:serve
   * @option port Port to start the dev server on.
   * @aliases wpsrv
   * @usage drush wepback:serve
   *   Serve the js files.
   */
  public function serve($options = ['port' => '1234']) {
    $this->output()->writeln('Hey! Starting the dev server.');
    try {
      $this->bundler->serve($options['port'], function ($type, $buffer) {
        $this->output()->writeln($buffer);
      });
    } catch (WebpackConfigNotValidException $e) {
      $this->output()->writeln($e->getMessage());
    } catch (WebpackConfigWriteException $e) {
      $this->output()->writeln($e->getMessage());
    } catch (NpmCommandFailedException $e) {
      $this->output()->writeln("The npm script has failed. Details:\n{$e->getMessage()}");
    }
  }

}
