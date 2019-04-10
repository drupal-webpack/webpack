<?php

namespace Drupal\webpack\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\npm\Exception\NpmCommandFailedException;
use Drupal\npm\Plugin\NpmExecutableNotFoundException;
use Drupal\webpack\BundlerInterface;
use Drupal\webpack\WebpackConfigNotValidException;
use Drupal\webpack\WebpackConfigWriteException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WebpackSettingsForm.
 */
class WebpackSettingsForm extends ConfigFormBase {

  /**
   * @var BundlerInterface
   */
  protected $bundler;

  /**
   * WebpackSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\webpack\BundlerInterface $bundler
   *   The webpack bundler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, BundlerInterface $bundler) {
    parent::__construct($config_factory);
    $this->bundler = $bundler;
  }

  /**
   * (@inheritDoc)
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('webpack.bundler')
    );
  }


  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'webpack.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webpack_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('webpack.settings');
    $form['output_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Output path'),
      '#description' => $this->t('Target directory for the built bundles. It can be a path or a stream uri.'),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('output_path'),
    ];
//    $form['build'] = [
//      '#type' => 'submit',
//      '#value' => $this->t('Build now'),
//      '#submit' => ['::buildNow'],
//    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $outputPath = trim($form_state->getValue('output_path'));
    if (strpos($outputPath, '/') === 0) {
      $form_state->setErrorByName('output_path', $this->t('Output path cannot be absolute. Please use either a scheme (eg. <em>public://</em> or a path relative to drupal root.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('webpack.settings')
      ->set('output_path', trim($form_state->getValue('output_path')))
      ->save();
  }
//
//  /**
//   * Builds the libraries.
//   *
//   * @param array $form
//   * @param \Drupal\Core\Form\FormStateInterface $form_state
//   */
//  public function buildNow(array &$form, FormStateInterface $form_state) {
//    $form_state->setRebuild(TRUE);
//    try {
//      list($status, $process, $messages) = $this->bundler->build();
//    } catch (NpmCommandFailedException $e) {
//    } catch (NpmExecutableNotFoundException $e) {
//    } catch (WebpackConfigNotValidException $e) {
//    } catch (WebpackConfigWriteException $e) {
//    }
//    foreach ($messages as $message) {
//      $this->messenger()->addMessage($message);
//    }
//  }

}
