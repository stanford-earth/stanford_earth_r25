<?php

namespace Drupal\stanford_earth_workgroups\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stanford_earth_workgroups\Service\StanfordEarthWorkgroupsService;

/**
 * Contains Drupal\stanford_earth_workgroups\Form\StanfordEarthWorkgroupsConfig.
 */
class StanfordEarthWorkgroupsConfig extends ConfigFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The workgroup service.
   *
   * @var \Drupal\stanford_earth_workgroups\Service\StanfordEarthWorkgroupsService
   */
  protected $wgService;

  /**
   * StanfordEarthWorkgroupsConfig constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactory interface.
   * @param \Drupal\stanford_earth_workgroups\Service\StanfordEarthWorkgroupsService $wgService
   *   The Workgroup service.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
    StanfordEarthWorkgroupsService $wgService) {
    $this->configFactory = $configFactory;
    $this->wgService = $wgService;
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('stanford_earth_workgroups.workgroup')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'stanford_earth_workgroups.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_workgroups_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('stanford_earth_workgroups.adminsettings');

    $form['stanford_earth_workgroups_cert'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MAIS Certificate Path'),
      '#description' => $this->t('Location on server of the MAIS WG API cert.'),
      '#required' => TRUE,
      '#default_value' => $config->get('stanford_earth_workgroups_cert'),
    ];

    $form['stanford_earth_workgroups_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MAIS Key Path'),
      '#description' => $this->t('Location on server of the MAIS WG API key.'),
      '#required' => TRUE,
      '#default_value' => $config->get('stanford_earth_workgroups_key'),
    ];

    $form['stanford_earth_workgroups_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Workgroup'),
      '#description' => $this->t('A Stanford Workgroup to test your cert and key.'),
      '#required' => TRUE,
      '#default_value' => $config->get('stanford_earth_workgroups_test'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $wg = $form_state->getValue('stanford_earth_workgroups_test');
    $wg_data = $this->wgService->getMembers($wg,
      $form_state->getValue('stanford_earth_workgroups_cert'),
      $form_state->getValue('stanford_earth_workgroups_key'));
    if (empty($wg_data['status']['member_count'])) {
      $form_state->setErrorByName('stanford_earth_workgroups_test',
        $wg_data['status']['message']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->configFactory->getEditable('stanford_earth_workgroups.adminsettings')
      ->set('stanford_earth_workgroups_test', $form_state->getValue('stanford_earth_workgroups_test'))
      ->set('stanford_earth_workgroups_cert', $form_state->getValue('stanford_earth_workgroups_cert'))
      ->set('stanford_earth_workgroups_key', $form_state->getValue('stanford_earth_workgroups_key'))
      ->save();
  }

}
