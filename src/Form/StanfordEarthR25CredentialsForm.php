<?php

namespace Drupal\stanford_earth_r25\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystem;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;

/**
 * Contains Drupal\stanford_earth_r25\Form\StanfordEarthR25ConfigForm.
 */
class StanfordEarthR25CredentialsForm extends ConfigFormBase {

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
   * StanfordEarthR25CredentialsForm constructor.
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
      'stanford_earth_r25.credentialsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_r25_credentials';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('stanford_earth_r25.credentialsettings');

    // start with an instructive string
    $markup_str = 'Enter your credentials for the R25 API.<br /> Don\'t have credentials? File a HelpSU ticket to Administrative Applications/25 Live.';

    // see if we can authenticate the current credentials and post a message if we can
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_result = $r25_service->r25_api_call('test');
    if ($r25_result['status']['status']) {
      $markup_str .= '<br /><br />Good news! Your credentials are set and valid and your site can currently connect to the R25 API.';
    }
    $form['description'] = array(
      '#markup' => t($markup_str),
    );

    $form['stanford_r25_credential'] = [
      '#type' => 'textfield',
      '#title' => $this->t('R25 Credential Path'),
      '#description' => $this->t('Location on server of a file containing your userid:password.'),
      '#required' => TRUE,
      '#default_value' => $config->get('stanford_r25_credential'),
    ];

    // base URL for calls to the 25Live API such as "https://webservices.collegenet.com/r25ws/wrd/stanford/run"
    $form['stanford_r25_base_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('Base URL for R25 Webs Services calls.'),
      '#default_value' => $config->get('stanford_r25_base_url'),
      '#required' => TRUE,
    );

    // A directory name to be created under the Drupal files directory for storage of location photos
    $roomphotos = $config->get('stanford_r25_room_image_directory');
    if (empty($roomphotos)) {
      $roomphotos = 'R25RoomPhotos';
    }
    $form['stanford_r25_room_image_directory'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Room Photos'),
      '#description' => $this->t('Directory under Drupal files directory for storage of R25 location photos.'),
      '#default_value' => $roomphotos,
      '#required' => FALSE,
    );

    /*
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
    $credential = $form_state->getValue('stanford_r25_credential');
    $base_url =  $form_state->getValue('stanford_r25_base_url');
    if (!empty($credential) && !empty($base_url)) {
      $data = ['credential' => $credential,
        'base_url' => $base_url];
      $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
      $data = serialize($data);
      $r25_result = $r25_service->r25_api_call('test', $data);
      if (!$r25_result['status']['status'] === true) {
        $form_state->setErrorByName('stanford_r25_credential',
          $this->t('Unable to connect to R25 API.'));
        $form_state->setErrorByName('stanford_r25_base_url',
          $this->t('Unable to connect to R25 API.'));
      }
    }

    $image_directory = $form_state->getValue('stanford_r25_room_image_directory');
    if (!empty($image_directory)) {
      $dirname = 'public://' . $image_directory;
      if (\Drupal::service('file_system')->prepareDirectory($dirname,
        FileSystem::CREATE_DIRECTORY) !== TRUE) {
        $form_state->setErrorByName('stanford_r25_room_image_directory',
          $this->t('Unable to create writable public directory.'));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->configFactory->getEditable('stanford_earth_r25.credentialsettings')
      ->set('stanford_r25_credential', $form_state->getValue('stanford_r25_credential'))
      ->set('stanford_r25_base_url', $form_state->getValue('stanford_r25_base_url'))
      ->set('stanford_r25_room_image_directory',
        $form_state->getValue('stanford_r25_room_image_directory'))
      ->save();
    // the credential we are using to access the 25Live api has an id number associated with it
    // get the 25Live account id# for this credential to use if setting todo's.
    $contact_id = false;
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_result = $r25_service->r25_api_call('acctinfo');
    if ($r25_result['status']['status'] === TRUE) {
      $results = $r25_result['output'];
      if (!empty($results['index']['R25:CONTACT_ID'][0])) {
        $contact_id = $results['vals'][$results['index']['R25:CONTACT_ID'][0]]['value'];
      }
    }
    if (!$contact_id) {
      $this->messenger()->addError($this->t('Unable to retrieve R25 Contact ID# for credential. Tentative Reservations will not create approval to-dos.'));
      $contact_id = '';
    }
    $this->configFactory->getEditable('stanford_earth_r25.credentialsettings')
      ->set('stanford_r25_credential_contact_id', $contact_id)
      ->save();
  }

}
