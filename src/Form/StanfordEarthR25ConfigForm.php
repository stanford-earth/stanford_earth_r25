<?php

namespace Drupal\stanford_earth_r25\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

/**
 * General configuration form for Room Reservations using R25.
 */
class StanfordEarthR25ConfigForm extends ConfigFormBase {

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
   * StanfordEarthR25ConfigForm constructor.
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

    $config = $this->config('stanford_earth_r25.adminsettings');

    // Various codes to be included in reservation requests, can be gotten by
    // calling various API URLs manually from a browser or, in some cases,
    // by looking at entries in 25Live administration console.
    $description = 'Various codes for your department that need to be sent along with room requests. ' .
      'This module currently only reserves rooms with the single org id and event type code specified here.';
    $form['codes'] = [
      '#type' => 'fieldset',
      '#title' => '25Live Codes',
      '#description' => $this->t("@description", ['@description' => $description]),
    ];
    $form['codes']['stanford_r25_org_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization ID'),
      '#default_value' => $config->get('stanford_r25_org_id'),
      '#required' => TRUE,
    ];
    $form['codes']['stanford_r25_event_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Type Code'),
      '#default_value' => $config->get('stanford_r25_event_type'),
      '#required' => TRUE,
    ];
    $form['codes']['stanford_r25_parent_event_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Parent Event ID'),
      '#default_value' => $config->get('stanford_r25_parent_event_id'),
      '#required' => TRUE,
    ];

    // General login settings to use when access is granted to Drupal users
    // via roles and permissions.
    $description = 'Use the "Book R25 Rooms" permission to restrict room reservations to specific roles. If ' .
      'anonymous users do not permission to book, the login link specified here will appear in place of the ' .
      'booking form.';
    $form['login'] = [
      '#type' => 'fieldset',
      '#title' => 'Booking Restrictions based on Drupal roles and permissions',
      '#description' => $this->t("@description", ['@description' => $description]),
    ];
    $login_msg = $config->get('stanford_r25_login_msg');
    if (empty($login_msg)) {
      $login_msg = 'Reserve this room';
    }
    $form['login']['stanford_r25_login_msg'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Anonymous User Login Message'),
      '#description' => $this->t('Potentially helpful reminder for anonymous users that may need to log in before being able to reserve rooms.'),
      '#default_value' => $login_msg,
    ];
    $login_uri = $config->get('stanford_r25_login_uri');
    if (empty($login_uri)) {
      $login_uri = '/user/login';
    }

    $form['login']['stanford_r25_login_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login URL'),
      '#description' => $this->t('Login URI for the reservation form if user is anonymous. Login message field must be set for this to show. Defaults to /user/login'),
      '#default_value' => $login_uri,
    ];
    $description = 'Contact information for room reservations will consist of the user account name and email ' .
      'address unless it is overridden by an implementation of hook_stanford_r25_contact_alter(&$contact_string). ' .
      'If multiple modules implement the hook, the value will be set by the last module invoked.';
    $form['login']['login_contact_info'] = [
      '#type' => 'markup',
      '#markup' => $description,
    ];

    // Advanced settings for external non-Drupal logins; may require 3rd party
    // contrib module for authentication sample module for authenticating
    // anonymous users, user0_webauth, is included in this package.
    $description = 'If you need to allow room reservations by users who authenticate through an external ' .
      'system but who do not get get Drupal accounts (for example, rooms bookable by entire campus versus ' .
      'rooms bookable only within the organization for which there are Drupal accounts) you must implement ' .
      'hooks for hook_stanford_r25_external_link and hook_stanford_r25_external_user. See the included ' .
      'user0_webauth module for an example.';
    $form['external'] = [
      '#type' => 'fieldset',
      '#title' => 'Advanced Restrictions',
      '#description' => $this->t("@description", ['@description' => $description]),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];
    $form['external']['stanford_r25_ext_login_msg'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unauthenticated User Login Message'),
      '#description' => $this->t('Potentially helpful reminder for anonymous users that may need to log in before being able to reserve rooms.'),
      '#default_value' => $login_msg,
    ];
    $form['external']['external_contact_info'] = [
      '#type' => 'markup',
      '#markup' => 'Contact information for room reservations will be blank unless it is set by an implementation of hook_stanford_r25_external_user_display(&$acct_array). If multiple modules implement the hook, the value will be set by the first module found.',
    ];

    // Messages if room is not reservable or user has no permission to reserve.
    $default_not_permitted = $config->get('stanford_r25_notpermitted_msg');
    if (empty($default_not_permitted)) {
      $default_not_permitted = [];
    }
    if (empty($default_not_permitted['value'])) {
      $default_not_permitted['value'] = '';
    }
    if (empty($default_not_permitted['format'])) {
      $default_not_permitted['format'] = filter_default_format();
    }
    $form['stanford_r25_notpermitted_msg'] = [
      '#type' => 'text_format',
      '#title' => $this->t('No Permission to Reserve Rooms Message'),
      '#description' => $this->t('Informational message to logged in users without the "Book R25 Rooms" permission.'),
      '#default_value' => $default_not_permitted['value'],
      '#format' => $default_not_permitted['format'],
      '#base_type' => 'textarea',
    ];
    // Messages if room is not reservable or user has no permission to reserve.
    $default_readonly_msg = $config->get('stanford_r25_readonly_msg');
    if (empty($default_readonly_msg)) {
      $default_readonly_msg = [];
    }
    if (empty($default_readonly_msg['value'])) {
      $default_readonly_msg['value'] = '';
    }
    if (empty($default_readonly_msg['format'])) {
      $default_readonly_msg['format'] = filter_default_format();
    }
    $form['stanford_r25_readonly_msg'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Read Only Calendar Message'),
      '#description' => $this->t('A message informing user that a room is not reservable.'),
      '#default_value' => $default_readonly_msg['value'],
      '#format' => $default_readonly_msg['format'],
      '#base_type' => 'textarea',
    ];

    // Default booking instructions to appear at bottom or reservation form.
    $default_booking_instr = $config->get('stanford_r25_booking_instructions');
    if (empty($default_booking_instr)) {
      $default_booking_instr = [];
    }
    if (empty($default_booking_instr['value'])) {
      $default_booking_instr['value'] = '';
    }
    if (empty($default_booking_instr['format'])) {
      $default_booking_instr['format'] = filter_default_format();
    }
    $form['stanford_r25_booking_instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Booking Instructions'),
      '#description' => $this->t('Instructions that will appear above room reservation forms.'),
      '#default_value' => $default_booking_instr['value'],
      '#format' => $default_booking_instr['format'],
      '#base_type' => 'textarea',
    ];

    // A list of blackout periods during which users will not be able to reserve
    // rooms that are configured to respect blackouts.
    $blackout_dates = $config->get('stanford_r25_blackout_dates');
    if (empty($blackout_dates)) {
      $blackout_dates = '';
    }
    elseif (is_array($blackout_dates)) {
      $blackout_dates_str = '';
      foreach ($blackout_dates as $value) {
        if (is_array($value)) {
          $blackout_dates_str .= $value['start'] . " - " . $value['end'] . "\r\n";
        }
      }
      $blackout_dates = $blackout_dates_str;
    }
    $form['stanford_r25_blackout_dates'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blackout Dates'),
      '#description' => $this->t('A list of blackout periods in the form "YYYY-MM-DD - YYYY-MM-DD" when this module may not make reservations for rooms marked as honoring blackouts. Does not use 25Live blackout periods because rooms may need to be reservable by other processes such as registrar room assignment.'),
      '#default_value' => $blackout_dates,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Validate numeric entries.
    $org_id = $form_state->getValue('stanford_r25_org_id');
    if (empty($org_id) || !is_numeric($org_id)) {
      $form_state->setErrorByName('stanford_r25_org_id',
        $this->t('Organization ID must be a number.'));
    }
    $event_type = $form_state->getValue('stanford_r25_event_type');
    if (empty($event_type) || !is_numeric($event_type)) {
      $form_state->setErrorByName('stanford_r25_event_type',
        $this->t('Event Type Code must be a number.'));
    }
    $parent_event = $form_state->getValue('stanford_r25_parent_event_id');
    if (empty($parent_event) || !is_numeric($parent_event)) {
      $form_state->setErrorByName('stanford_r25_parent_event_id',
        $this->t('Parent Event ID must be a number.'));
    }
    // Validate blackout date list.
    $blackouts = $form_state->getValue('stanford_r25_blackout_dates');
    if (!empty($blackouts)) {
      $blackouts = StanfordEarthR25Util::stanfordR25ParseBlackoutDates($blackouts);
      if (empty($blackouts)) {
        $form_state->setErrorByName('stanford_r25_blackout_dates',
          $this->t('Blackout dates must be in the form "YYYY-MM-DD - YYYY-MM-DD".'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory->getEditable('stanford_earth_r25.adminsettings');
    $form_values = $form_state->getValues();
    foreach ($form_values as $key => $value) {
      if (substr($key, 0, 13) == 'stanford_r25_') {
        if ($key == 'stanford_r25_blackout_dates') {
          $value = StanfordEarthR25Util::stanfordR25ParseBlackoutDates($value);
        }
        $config->set($key, $value);
      }
    }
    $config->save();
  }

}
