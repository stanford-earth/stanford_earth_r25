<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Example add and edit forms.
 */
class StanfordEarthR25LocationForm extends EntityForm {

  /**
   * Constructs an RoomForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $location = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $location->label(),
      '#description' => $this->t("Label for the Location."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $location->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$location->isNew(),
    ];

    // Whether the calendar page uses 25Live Publisher embeds or fullcalendar.
    // Only fullcalendar allows selection of dates, times, and durations from
    // the calendar.
    $form['caltype'] = [
      '#type' => 'radios',
      '#title' => $this->t('Calendar Display Options'),
      '#default_value' => $location->get('caltype'),
      '#options' => array(1 => '25Live Publisher', 2 => 'FullCalendar'),
      '#description' => $this->t('Whether to use the 25Live Publisher read-only calendar display or the interactive FullCalendar display.'),
      '#required' => TRUE,
      ];

    // room status - whether it should display a calendar and allow reservations, display a calendar without allowing reservations,
    // or display a calendar and allow tentative or confirmed reservations
    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Room Display Options'),
      '#default_value' => $location->get('status'),
      '#options' => array(
        0 => 'Disabled',
        1 => 'Read-Only Calendar',
        2 => 'Tentative Bookings',
        3 => 'Confirmed Bookings'
      ),
      '#description' => $this->t('Whether to just display a calendar or allow tentative or confirmed bookings.'),
      '#required' => TRUE,
    ];

    // whether the initial calendar display is Month, Week, or Day
    $form['default_view'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default Calendar View'),
      '#default_value' => $location->get('default_view'),
      '#options' => array(1 => 'Daily', 2 => 'Weekly', 3 => 'Monthly'),
      '#description' => $this->t('Whether the initial view of a calendar page should be a monthly, weekly, or daily view. Applies only to FullCalendar.'),
      '#required' => TRUE,
    ];

    // Maximum number of hours for a booking. Ignored for multi-day bookable rooms
    $form['max_hours'] = [
      '#title' => $this->t('Maximum Reservation (Hours)'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#size' => 30,
      '#default_value' => $location->get('max_hours'),
      '#description' => $this->t('The maximum number of hours for a reservation via this interface. Set to 0 for no limit.'),
    ];

    // 25Live Publisher name of calendar if using it; otherwise leave blank
    $form['spud_name'] = [
      '#title' => $this->t('25Live Publisher Webname'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $location->get('spud_name'),
      '#description' => $this->t('The 25Live Publisher webname or "spud" name for this room\'s public calendar display. Required for 25Live Publisher display.'),
      '#states' => array(
        'required' => array(
          ':input[name="caltype"]' => array('value' => 1)
        )
      ),
    ];

    // Internal 25Live "space id" for the room. Can be found in results from call to https://webservices.collegenet.com/r25ws/wrd/stanford/run/spaces.xml
    // (replacing stanford with your organization name)
    $form['space_id'] = array(
      '#title' => $this->t('R25 Room ID'),
      '#type' => 'textfield',
      '#reqired' => TRUE,
      '#size' => 30,
      '#default_value' => $location->get('space_id'),
      '#description' => $this->t('The R25 space_id code for this room. Required for tentative and confirmed bookings.'),
      '#states' => array(
        'required' => array(
          ':input[name="status"]' => array(
            array('value' => 2),
            array('value' => 3)
          )
        )
      ),
    );
    // the event description field found in the 25Live event wizard allows more characters than the event title
    $form['description_as_title'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Show event description as event name in FullCalendar'),
      '#return_value' => 1,
      '#default_value' => $location->get('description_as_title'),
      '#description' => $this->t("Check if you would like to use the Event Description field instead of the Event Name in the FullCalendar time slot."),
    );
    // give users a way to get back to a particular fullcalendar date and view
    $form['permalink'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Show a permlink on FullCalendar pages for the view and date'),
      '#return_value' => 1,
      '#default_value' => $location->get('permalink'),
      '#description' => $this->t('Useful if you want to distribute links to specific calendar pages to people.'),
    );
    // have room reservations obey the blackout periods specified for the entire site
    $form['honor_blackouts'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Honor Blackout Dates for Reservations'),
      '#return_value' => 1,
      '#default_value' => $location->get('honor_blackouts'),
      '#description' => $this->t('Only allow reservations if current and requested dates are after the end of the most ' .
        'recent blackout period and before the start of the next blackout period.'),
    );
    // the name of the 25Live security group (ususally the same as a Stanford workgroup, but not necessarily so)
    // this will be used to generate and cache a list of email addresses to be contacted for tentative reservations
    $form['approver_secgroup_name'] = array(
      '#title' => $this->t('Approver Security Group'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $location->get('approver_secgroup_name'),
      '#description' => $this->t('The R25 Security Group, also a Stanford Workgroup, of those who can approve tentative ' .
        'reservation requests. All members of this group will receive email with the request information. '),
    );
    // the 25Live security group id that corresponds to the security group name. looked up on form submit.
    $form['approver_secgroup_id'] = array(
      '#title' => $this->t('Approver Security Group ID'),
      '#type' => 'hidden',
      '#size' => 30,
      '#default_value' => $location->get('approver_secgroup_id'),
      '#description' => $this->t('The corresponding 25Live id number for the security group specified above.'),
    );
    // whether to email members of the security group and additional email list when an event is canceled or confirmed
    // through this website
    $form['email_confirms_and_cancels'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Email cancellations to approvers'),
      '#return_value' => 1,
      '#default_value' => $location->get('email_confirms_and_cancels'),
      '#description' => $this->t('Check if room approvers should receive an email when a user self-service cancels a reservation.'),
    );
    // additional email addresses to get confirm,cancel, and tentative reservation emails.
    $form['email_list'] = array(
      '#title' => $this->t('Email List'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $location->get('email_list'),
      '#description' => $this->t('Comma-separated list of email addresses which should receive notification of any reservation requests. Leave blank for "none".'),
    );

    // a fieldset of rarely-needed, advanced settings
    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced Options'),
      '#description' => $this->t('Some uncommonly used options.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    // whether the room allows reservations only by drupal permission, or only by external authentication, or either
    $form['advanced']['authentication'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Authentication Method'),
      '#default_value' => $location->get('authentication'),
      '#options' => array(
        1 => 'Internal (Drupal) Accounts',
        2 => 'External (Non-Drupal) Login',
        3 => 'Both Internal and External'
      ),
      '#description' => $this->t('Whether the room is bookable based on Drupal accounts, roles, and permissions or through external means.'),
    );
    // whether the room may be reserved for multiple-day events
    $form['advanced']['multi_day'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multi-day reservations'),
      '#description' => $this->t('Start time will refer to first day; end-time will refer to last day; days in-between will be full days.'),
      '#default_value' => $location->get('multi_day'),
    );
    // whether there are additional 25Live Event Custom Attribute fields that should be added to the reservation form.
    // provide the comma-separated ids for the wanted attributes; see attribute list at
    // https://webservices.collegenet.com/r25ws/wrd/stanford/run/evatrb.xml (substitute your inst for 'stanford'
    $form['advanced']['event_attributes'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Event Attributes'),
      '#default_value' => $location->get('event_attributes'),
      '#description' => $this->t('If custom attribute fields need to be included on the reservation form, enter their R25 ' .
        'id codes as a comma-separated list. Put an asterisk after a number to indicate a required field.'),
    );
    // whether there is an additional contact attribute defined for your event; provide the attribute id as above
    $form['advanced']['contact_attr'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Contact Attribute'),
      '#default_value' => $location->get('contact_attr'),
      '#description' => $this->t("Event Custom Attribute in which we will store the user's contact information."),
    );
    // a billing code to use if you want to auto-select a billing code for this event
    $form['advanced']['auto_billing_code'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Auto-Bill Rate Group ID'),
      '#default_value' => $location->get('auto_billing_code'),
      '#description' => $this->t("The rate group id to use to auto-bill for the use of this room. Leave blank for none."),
    );
    // override default booking instructions for this room
    $default_booking_instr = $config['override_booking_instructions'];
    $form['advanced']['override_booking_instructions'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Override Booking Instructions'),
      '#description' => $this->t('Instructions that will appear below room reservation forms if different from site default.'),
      '#default_value' => $location->get('value'),
      '#format' => $default_booking_instr['format'],
    );
    // checkbox if you want to store booking information in $form_state['storage'] for post-processing in your own module
    $form['advanced']['postprocess_booking'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Postprocess Booking'),
      '#description' => $this->t("If you want to write you own submit hook to do something after a booking is complete, check this box and booking info will be placed in \$form_state['storage']"),
      '#default_value' => $location->get('postprocess_booking'),
    );
    $form['content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Field content'),
      '#default_value' => isset($field['properties']['content']['value']) ? $field['properties']['content']['value'] : '',
      '#format' => isset($field['properties']['content']['format']) ? $field['properties']['content']['format'] : 'plain_text',
      '#base_type' => 'textarea',
      '#required' => TRUE,
    ];
/*
    // override default booking instructions for this room
    $default_blackout_instr = $config['override_blackout_instructions'];
    $form['advanced']['override_blackout_instructions'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Override Blackout Instructions'),
      '#description' => $this->t('User instructions for booking during blackout periods, if "Honor Blackout Dates" is checked. Default site message displayed if left blank.'),
      '#default_value' => $default_blackout_instr['value'],
      '#format' => $default_blackout_instr['format'],
    );
*/
    // set a created date
    $form['created'] = array(
      '#title' => $this->t('Created on'),
      '#type' => 'textfield',
      '#default_value' => $config['created'],
      '#disabled' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $location = $this->entity;
    $status = $location->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('%Location label created.', [
        '%label' => $location->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Location %label updated.', [
        '%label' => $location->label(),
      ]));
    }

    $form_state->setRedirect('entity.stanford_earth_r25_location.collection');
  }

  /**
   * Helper function to check whether a Location configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('stanford_earth_r25_location')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
