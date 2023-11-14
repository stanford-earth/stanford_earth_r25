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
   * The Drupal file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * Class constructor.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    StanfordEarthR25Service $r25Service,
    FileSystem $fileSystem) {
    $this->configFactory = $configFactory;
    $this->r25Service = $r25Service;
    $this->fileSystem = $fileSystem;
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('stanford_earth_r25.r25_call'),
      $container->get('file_system')
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

    // Start with an instructive string.
    $markup_str = 'Enter your credentials for the R25 API.<br /> Don\'t have credentials? File a HelpSU ticket to Administrative Applications/25 Live.';

    // See if we can authenticate the current credentials.
    $r25_result = $this->r25Service->stanfordR25ApiCall('test');
    if ($r25_result['status']['status']) {
      $markup_str .= '<br /><br />Good news! Your credentials are set and valid and your site can currently connect to the R25 API.';
    }
    $form['description'] = [
      '#markup' => $markup_str,
    ];

    $form['stanford_r25_credential'] = [
      '#type' => 'textfield',
      '#title' => $this->t('R25 Credential Path'),
      '#description' => $this->t('Location on server of a file containing your userid:password.'),
      '#required' => TRUE,
      '#default_value' => $config->get('stanford_r25_credential'),
    ];

    // Base URL for calls to the 25Live API such as
    // "https://webservices.collegenet.com/r25ws/wrd/stanford/run".
    $form['stanford_r25_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('Base URL for R25 Webs Services calls.'),
      '#default_value' => $config->get('stanford_r25_base_url'),
      '#required' => TRUE,
    ];

    // A directory name to be created under the Drupal files directory for
    // storage of location photos.
    $roomphotos = $config->get('stanford_r25_room_image_directory');
    if (empty($roomphotos)) {
      $roomphotos = 'R25RoomPhotos';
    }
    $form['stanford_r25_room_image_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Room Photos'),
      '#description' => $this->t('Directory under Drupal files directory for storage of R25 location photos.'),
      '#default_value' => $roomphotos,
      '#required' => FALSE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $credential = $form_state->getValue('stanford_r25_credential');
    $base_url = $form_state->getValue('stanford_r25_base_url');
    if (!empty($credential) && !empty($base_url)) {
      $data =
        [
          'credential' => $credential,
          'base_url' => $base_url,
        ];
      $data = serialize($data);
      $r25_result = $this->r25Service->stanfordR25ApiCall('test', $data);
      if (!$r25_result['status']['status'] === TRUE) {
        $form_state->setErrorByName('stanford_r25_credential',
          $this->t('Unable to connect to R25 API.'));
        $form_state->setErrorByName('stanford_r25_base_url',
          $this->t('Unable to connect to R25 API.'));
      }
    }

    $image_directory = $form_state->getValue('stanford_r25_room_image_directory');
    if (!empty($image_directory)) {
      $dirname = 'public://' . $image_directory;
      if ($this->fileSystem->prepareDirectory($dirname,
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
    // The credential we are using to access the 25Live api has an id number
    // associated with it get the 25Live account id# for this credential to use
    // if setting todo's.
    $contact_id = FALSE;
    $r25_result = $this->r25Service->stanfordR25ApiCall('acctinfo');
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
    $this->configFactory->getEditable('stanford_earth_r25.adminsettings')
      ->set('stanford_r25_credential_contact_id', $contact_id)
      ->save();
  }

}
