<?php

namespace Drupal\webpack\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class WebpackSettingsForm.
 */
class WebpackSettingsForm extends ConfigFormBase {

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

}
