<?php

namespace Drupal\stanford_earth_r25\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Symfony\Component\Yaml\Yaml;

/**
 * Contains Drupal\stanford_earth_r25\Form\StanfordEarthR25Config.
 */
class StanfordEarthR25Config extends ConfigFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The R25 service.
   *
   * @var \Drupal\stanford_earth_r25\Service\StanfordEarthR25Service
   */
  protected $r25Service;

  /**
   * StanfordEarthR25Config constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactory interface.
   * @param \Drupal\stanford_earth_r25\Service\StanfordEarthR25Service $r25Service
   *   The Workgroup service.
   */
  public function __construct(ConfigFactoryInterface $configFactory,
    StanfordEarthR25Service $r25Service) {
    $this->configFactory = $configFactory;
    $this->r25Service = $r25Service;
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('stanford_earth_r25.r25_call')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'stanford_earth_r25.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_r25_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rooms = [
      'langcode' => 'en',
      'status' => 'true',
      'dependencies' => [],
      'id' => 'stanford_earth_r25_rooms',
      'label' => 'Stanford Earth R25 Rooms',
      'description' => 'Stanford Earth R25 Room Configurations',
      'source_type' => null,
      'module' => null,
    ];

    $db = \Drupal::database();
    $result = $db->query("SELECT value FROM {room_test} WHERE name = :sunetid", [':sunetid' => 'r25_room']);
    foreach ($result as $record) {
      $rooms['rooms'] = unserialize($record->value);
    }

    //$dumper = new Yaml();
    //$room_yaml = $dumper->dump($rooms);
    $room_yaml = Yaml::dump($rooms,10,2);

    $config = $this->config('stanford_earth_r25.adminsettings');
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    //$r25_result = $r25_service->r25_api_call();
    $args = 'space_id=1675&scope=extended&start_dt=20210801T00000000&end_dt=20210901T00000000';
    $items = array();
    // make the API call
    $r25_result = $r25_service->r25_api_call('feed', $args);

    $xyz = 1;

      /*
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

      */
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
/*
    $wg = $form_state->getValue('stanford_earth_workgroups_test');
    $wg_data = $this->wgService->getMembers($wg,
      $form_state->getValue('stanford_earth_workgroups_cert'),
      $form_state->getValue('stanford_earth_workgroups_key'));
    if (empty($wg_data['status']['member_count'])) {
      $form_state->setErrorByName('stanford_earth_workgroups_test',
        $wg_data['status']['message']);
    }
*/
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
/*
    $this->configFactory->getEditable('stanford_earth_workgroups.adminsettings')
      ->set('stanford_earth_workgroups_test', $form_state->getValue('stanford_earth_workgroups_test'))
      ->set('stanford_earth_workgroups_cert', $form_state->getValue('stanford_earth_workgroups_cert'))
      ->set('stanford_earth_workgroups_key', $form_state->getValue('stanford_earth_workgroups_key'))
      ->save();
*/
  }

}
