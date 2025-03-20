<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\user\Entity\Role;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

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
  private function checkRadioVals($inputval="", $min=0, $max=0) {
    $outputval = strval($min);
    if (is_numeric($inputval)) {
      $intval = intval($inputval);
      if ($intval < $min || $intval > $max) {
        $intval = $min;
      }
      $outputval = strval($intval);
    }
    return $outputval;
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

    // Location type - is this location a Meeting Room, Lab/Seminar Room,
    // Event Space, Vehicle, or Unknown.
    $locationtype = $this->checkRadioVals($location->get('locationtype'), 0,4);
    $form['locationtype'] = [
      '#type' => 'radios',
      '#title' => $this->t('Location Reporting Type'),
      '#default_value' => $locationtype,
      '#options' => [
        0 => $this->t('Unknown'),
        1 => $this->t('Meeting Room'),
        2 => $this->t('Lab/Seminar Room'),
        3 => $this->t('Event Space'),
        4 => $this->t('Vehicle'),
      ],
      '#description' => $this->t('The reporting type of this location for statistical purposes.'),
      '#required' => TRUE,
    ];

    // Room display type - whether it should display a calendar and allow
    // reservations, display a calendar without allowing reservations, or
    // display a calendar and allow tentative or confirmed reservations.
    $displaytype = $this->checkRadioVals($location->get('displaytype'), 0,3);
    $form['displaytype'] = [
      '#type' => 'radios',
      '#title' => $this->t('Room Display Options'),
      '#default_value' => $displaytype,
      '#options' => [
        0 => $this->t('Disabled'),
        1 => $this->t('Read-Only Calendar'),
        2 => $this->t('Tentative Bookings'),
        3 => $this->t('Confirmed Bookings'),
      ],
      '#description' => $this->t('Whether to just display a calendar or allow tentative or confirmed bookings.'),
      '#required' => TRUE,
    ];

    // Whether the initial calendar display is Month, Week, or Day.
    $defaultView = $this->checkRadioVals($location->get('default_view'), 1, 3);
    $form['default_view'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default Calendar View'),
      '#default_value' => $defaultView,
      '#options' => [
        1 => $this->t('Daily'),
        2 => $this->t('Weekly'),
        3 => $this->t('Monthly'),
      ],
      '#description' => $this->t('Whether the initial view of a calendar page should be a monthly, weekly, or daily view. Applies only to FullCalendar.'),
      '#required' => TRUE,
    ];

    // Maximum number of hours for a booking. Ignored for multi-day bookable.
    $maxHours = $location->get('max_hours');
    if (empty($maxHours)) {
      $maxHours = "2";
    }
    $form['max_hours'] = [
      '#title' => $this->t('Maximum Reservation (Hours)'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#size' => 30,
      '#default_value' => $maxHours,
      '#description' => $this->t('The maximum number of hours for a reservation via this interface. Set to 0 for no limit.'),
    ];

    // Internal 25Live "space id" for the room. Can be found in results from
    // call to
    // https://webservices.collegenet.com/r25ws/wrd/stanford/run/spaces.xml
    // (replacing stanford with your organization name).
    $form['space_id'] = [
      '#title' => $this->t('R25 Room ID'),
      '#type' => 'textfield',
      '#reqired' => TRUE,
      '#size' => 30,
      '#default_value' => $location->get('space_id'),
      '#description' => $this->t('The R25 space_id code for this room. Required for tentative and confirmed bookings.'),
      '#states' => [
        'required' => [
          ':input[name="displaytype"]' => [
            ['value' => 2],
            ['value' => 3],
          ],
        ],
      ],
    ];

    // Have room reservations obey site-wide blackout periods.
    $form['honor_blackouts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Honor Blackout Dates for Reservations'),
      '#return_value' => 1,
      '#default_value' => $location->get('honor_blackouts'),
      '#description' => $this->t('Only allow reservations if current and requested dates are after the end of the most recent blackout period and before the start of the next blackout period.'),
    ];

    // The name of the 25Live security group (ususally the same as a Stanford
    // workgroup, but not necessarily so). This will be used to generate and
    // cache a list of email addresses to contact for tentative reservations.
    $form['approver_secgroup_name'] = [
      '#title' => $this->t('Approver Security Group'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $location->get('approver_secgroup_name'),
      '#description' => $this->t('The R25 Security Group, also a Stanford Workgroup, of those who can approve tentative reservation requests. All members of this group will receive email with the request information.'),
    ];

    // The 25Live security group id that corresponds to the security group name.
    // Looked up on form submit.
    $form['approver_secgroup_id'] = [
      '#title' => $this->t('Approver Security Group ID'),
      '#type' => 'hidden',
      '#size' => 30,
      '#default_value' => $location->get('approver_secgroup_id'),
      '#description' => $this->t('The corresponding 25Live id number for the security group specified above.'),
    ];

    // Whether to email members of the security group and additional email
    // list when an event is canceled or confirmed through this website.
    $form['email_cancellations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email cancellations to approvers'),
      '#return_value' => 1,
      '#default_value' => $location->get('email_cancellations'),
      '#description' => $this->t('Check if room approvers should receive an email when a user self-service cancels a reservation.'),
    ];

    // Additional email addresses to get confirm,cancel,
    // and tentative reservation emails.
    $form['email_list'] = [
      '#title' => $this->t('Email List'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $location->get('email_list'),
      '#description' => $this->t('Comma-separated list of email addresses which should receive notification of any reservation requests. Leave blank for "none".'),
    ];

    // A fieldset of rarely-needed, advanced settings.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Options'),
      '#description' => $this->t('Some uncommonly used options.'),
      '#open' => FALSE,
    ];

    // Whether the calendar page uses 25Live Publisher embeds or fullcalendar.
    // Only fullcalendar allows selection of dates, times, and durations from
    // the calendar.
    $caltype = $this->checkRadioVals($location->get('caltype'), 1, 3);
    $form['advanced']['caltype'] = [
      '#type' => 'radios',
      '#title' => $this->t('Calendar Display Options'),
      '#default_value' => $caltype,
      '#options' => [
        1 => $this->t('25Live Publisher'),
        2 => $this->t('FullCalendar'),
        3 => $this->t('FullCalendar List'),
      ],
      '#description' => $this->t('Whether to use the 25Live Publisher read-only calendar display or the interactive FullCalendar display.'),
      '#required' => TRUE,
    ];

    // 25Live Publisher name of calendar if using it; otherwise leave blank.
    $form['advanced']['spud_name'] = [
      '#title' => $this->t('25Live Publisher Webname'),
      '#type' => 'textfield',
      '#size' => 30,
      '#default_value' => $location->get('spud_name'),
      '#description' => $this->t('The 25Live Publisher webname or "spud" name for this room\'s public calendar display. Required for 25Live Publisher display.'),
      '#states' => [
        'required' => [
          ':input[name="caltype"]' => ['value' => 1],
        ],
      ],
    ];

    // The event description field found in the 25Live event wizard allows more
    // characters than the event title.
    $form['advanced']['description_as_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show event description as event name in FullCalendar'),
      '#return_value' => 1,
      '#default_value' => $location->get('description_as_title'),
      '#description' => $this->t("Check if you would like to use the Event Description field instead of the Event Name in the FullCalendar time slot."),
    ];

    // Give users a way to get back to a particular fullcalendar date and view.
    $form['advanced']['permalink'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a permlink on FullCalendar pages for the view and date'),
      '#return_value' => 1,
      '#default_value' => $location->get('permalink'),
      '#description' => $this->t('Useful if you want to distribute links to specific calendar pages to people.'),
    ];

    // Whether the room may be reserved for multiple-day events.
    $form['advanced']['multi_day'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multi-day reservations'),
      '#description' => $this->t('Start time will refer to first day; end-time will refer to last day; days in-between will be full days.'),
      '#default_value' => $location->get('multi_day'),
    ];

    // Whether there are additional 25Live Event Custom Attribute fields that
    // should be added to the reservation form. Provide the comma-separated ids
    // for the wanted attributes; see attribute list at
    // https://webservices.collegenet.com/r25ws/wrd/stanford/run/evatrb.xml
    // (substitute your inst for 'stanford'.
    $form['advanced']['event_attributes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Attributes'),
      '#default_value' => $location->get('event_attributes'),
      '#description' => $this->t('If custom attribute fields need to be included on the reservation form, enter their R25 id codes as a comma-separated list. Put an asterisk after a number to indicate a required field.'),
    ];
    // Whether there is an additional contact attribute defined for your event;
    // provide the attribute id as above.
    $form['advanced']['contact_attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Attribute'),
      '#default_value' => $location->get('contact_attribute'),
      '#description' => $this->t("Event Custom Attribute in which we will store the user's contact information."),
    ];
    // A billing code to use if you want to auto-select a billing code
    // for this event.
    $form['advanced']['auto_billing_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auto-Bill Rate Group ID'),
      '#default_value' => $location->get('auto_billing_code'),
      '#description' => $this->t("The rate group id to use to auto-bill for the use of this room. Leave blank for none."),
    ];
    // Override default booking instructions for this room.
    $override_instr = $location->get('override_booking_instructions');
    if (empty($override_instr)) {
      $override_instr = [];
    }
    if (empty($override_instr['value'])) {
      $override_instr['value'] = '';
    }
    if (empty($override_instr['format'])) {
      $override_instr['format'] = filter_default_format();
    }
    $form['advanced']['override_booking_instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Override Booking Instructions'),
      '#description' => $this->t('Instructions that will appear below room reservation forms if different from site default.'),
      '#default_value' => $override_instr['value'],
      '#format' => $override_instr['format'],
      '#base_type' => 'textarea',
    ];

    // Checkbox if you want to store booking information in
    // $form_state['storage'] for post-processing in your own module.
    $form['advanced']['postprocess_booking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Postprocess Booking'),
      '#description' => $this->t("If you want to write you own submit hook to do something after a booking is complete, check this box and booking info will be placed in \$form_state['storage']"),
      '#default_value' => $location->get('postprocess_booking'),
    ];
    // Override default blackout instructions for this room.
    $override_instr = $location->get('override_blackout_instructions');
    if (empty($override_instr)) {
      $override_instr = [];
    }
    if (empty($override_instr['value'])) {
      $override_instr['value'] = '';
    }
    if (empty($override_instr['format'])) {
      $override_instr['format'] = filter_default_format();
    }
    $form['advanced']['override_blackout_instructions'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Override Blackout Instructions'),
      '#description' => $this->t('User instructions for booking during blackout periods, if "Honor Blackout Dates" is checked. Default site message displayed if left blank.
'),
      '#default_value' => $override_instr['value'],
      '#format' => $override_instr['format'],
      '#base_type' => 'textarea',
    ];

    // Room labels for multi-room legend in form label1+label2+label3.
    $form['advanced']['legend_labels'] = [
      '#title' => $this->t('Multi-Room Legend Labels'),
      '#type' => 'textfield',
      '#size' => 255,
      '#default_value' => $location->get('legend_labels'),
      '#description' => $this->t('For use on multi-room calendars in the form label1+label2+label3'),
    ];

    // Checkbox if you want to the reservation form for the location
    // to appear on a new page instead of a pop-up form.
    $form['advanced']['nopopup_reservation_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('No Pop-up Reservation Form'),
      '#description' => $this->t("Check this box if reservation form for this location should be on a new page instead of a pop-up."),
      '#default_value' => $location->get('nopopup_reservation_form'),
    ];

    // Checkbox if you want to the reservation form for the location
    // to appear on a new page instead of a pop-up form.
    $form['advanced']['hide_titles_for_non_managers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide Event Titles for Unauthorized Users'),
      '#description' => $this->t("Check this box if event titles should be hidden on calendars."),
      '#default_value' => $location->get('hide_titles_for_non_managers'),
    ];

    $form['advanced']['override_organization_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Organization ID'),
      '#description' => $this->t("Enter a 25Live Organization ID for this location if different from the system default."),
      '#default_value' => $location->get('override_organization_id'),
      '#required' => false,
    ];

    $form['advanced']['override_event_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Event Type Code'),
      '#description' => $this->t("Enter a 25Live Event Code for this location if different from the system default."),
      '#default_value' => $location->get('override_event_code'),
      '#required' => false,
    ];

    // A list of allowed date ranges for booking
    $allowed_dates = $location->get('allowed_dates');
    if (empty($allowed_dates)) {
      $allowed_dates = '';
    }
    elseif (is_array($allowed_dates)) {
      $allowed_dates_str = '';
      foreach ($allowed_dates as $value) {
        if (is_array($value)) {
          $allowed_dates_str .= $value['start'] . " - " . $value['end'] . "\r\n";
        }
      }
      $allowed_dates = $allowed_dates_str;
    }
    $form['advanced']['allowed_dates'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed Dates'),
      '#description' => $this->t('A list of date ranges in the form "YYYY-MM-DD - YYYY-MM-DD" when this module may make reservations. Does not use 25Live blackout periods because rooms may need to be reservable by other processes such as registrar room assignment.'),
      '#default_value' => $allowed_dates,
    ];

    // A list of allowed timeslots for booking
    $allowed_timeslots = $location->get('allowed_timeslots');
    if (empty($allowed_timeslots)) {
      $allowed_timeslots = '';
    }
    elseif (is_array($allowed_timeslots)) {
      $allowed_timeslots_str = '';
      foreach ($allowed_timeslots as $value) {
        $timeslot_str = '';
        if (is_array($value)) {
          if (!empty($value['days']) && is_array($value['days'])) {
            foreach ($value['days'] as $day) {
              if (!empty($day)) {
                if (!empty($timeslot_str)) {
                  $timeslot_str .= ',';
                }
                $timeslot_str .= $day;
              }
            }
            if (!empty($timeslot_str)) {
              $timeslot_str .= '|';
            }
            if (!empty($value['start'])) {
              $timeslot_str .= $value['start'] . '|';
            }
            if (!empty($value['end'])) {
              $timeslot_str .= $value['end'];
            }
            if (!empty($value['price'])) {
              $timeslot_str .= '|' . $value['price'];
            }
            $timeslot_str .= "\r\n";
          }
        }
        $allowed_timeslots_str .= $timeslot_str;
      }
      $allowed_timeslots = $allowed_timeslots_str;
    }
    $form['advanced']['allowed_timeslots'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed Timslots'),
      '#description' => $this->t('A list of allowed timeslots blah blah blah.'),
      '#default_value' => $allowed_timeslots,
    ];

    // Get the user roles.
    //$roles = user_roles();
    $roles = Role::loadMultiple();
    $roleOptions = [];
    foreach ($roles as $rid => $role) {
      $roleOptions[$rid] = Html::escape($role->label());
    }

    // Allow override of which roles can view this location.
    $overrideViewRoles = $location->get('override_view_roles');
    if (empty($overrideViewRoles) || !is_array($overrideViewRoles)) {
      $overrideViewRoles = [];
    }
    $form['advanced']['override_view_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Override Location View Roles'),
      '#options' => $roleOptions,
      '#default_value' => $overrideViewRoles,
      '#description' => $this->t("Choose which roles can view this location's calendar. Completely overrides roles set for this permission in Drupal permissions. Leave all blank to use Drupal permissions."),
    ];

    // Allow override of which roles can book this location.
    $overrideBookRoles = $location->get('override_book_roles');
    if (empty($overrideBookRoles) || !is_array($overrideBookRoles)) {
      $overrideBookRoles = [];
    }
    $form['advanced']['override_book_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Override Location Booking Roles'),
      '#options' => $roleOptions,
      '#default_value' => $overrideBookRoles,
      '#description' => $this->t("Choose which roles can book this location. Completely overrides roles set for this permission in Drupal permissions. Leave all blank to use Drupal permissions."),
    ];

    // Set a created date.
    $form['updated'] = [
      '#title' => $this->t('Last updated'),
      '#type' => 'textfield',
      '#default_value' => $location->get('updated'),
      '#disabled' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $displaytype = intval($form_state->getValue('displaytype', 0));
    $caltype = intval($form_state->getValue('caltype', 0));
    $spud = $form_state->getValue('spud_name');
    if (empty($spud) && $caltype == 1 && $displaytype > 0) {
      $form_state->setErrorByName('spud_name', '25Live Publisher webname is required to display calendar.');
    }
    $space_id = $form_state->getValue('space_id');
    if (empty($space_id) && $displaytype > 1) {
      $form_state->setErrorByName('space_id', 'R25 Room ID is required to enable bookings.');
    }
    $max_hours = $form_state->getValue('max_hours', -1);
    if (!is_numeric($max_hours) || intval($max_hours) != $max_hours || $max_hours < 0) {
      $form_state->setErrorByName('max_hours', 'Maximum Reservation (Hours) must be zero or a positive integer.');
    }
    $override_org_id = $form_state->getValue('override_organization_id');
    if (!empty($override_org_id) && !is_numeric($override_org_id)) {
      $form_state->setErrorByName('override_organization_id', 'Override Organization ID must be numeric.');
    }
    $override_event_code = $form_state->getValue('override_event_code');
    if (!empty($override_event_code) && !is_numeric($override_event_code)) {
      $form_state->setErrorByName('override_event_code', 'Override Event Code must be numeric.');
    }

    $secgroup_id = 0;
    $secgroup_name = $form_state->getValue('approver_secgroup_name');
    if (!empty($secgroup_name)) {
      $secgroup_id = StanfordEarthR25Util::stanfordR25SecgroupId($secgroup_name);
      if (empty($secgroup_id)) {
        $form_state->setErrorByName('approver_secgroup_name', 'Unable to retrieve security group id from 25Live.');
      }
      $form_state->setValue('approver_secgroup_id', $secgroup_id);
      $list = StanfordEarthR25Util::stanfordR25SecurityGroupEmails($secgroup_id, TRUE);
      if (empty($list)) {
        $form_state->setErrorByName('approver_secgroup_name', 'Unable to retrieve security group email list from 25Live.');
      }
    }

    // Validate allowed date ranges.
    $allowed = $form_state->getValue('allowed_dates');
    if (!empty($allowed)) {
      $allowed = StanfordEarthR25Util::stanfordR25ParseBlackoutDates($allowed);
      if (empty($allowed)) {
        $form_state->setErrorByName('allowed_dates',
          $this->t('Allowed date ranges must be in the form "YYYY-MM-DD - YYYY-MM-DD".'));
      }
    }
    // Validate allowed timeslots.
    $allowed = $form_state->getValue('allowed_timeslots');
    if (!empty($allowed)) {
      $result = StanfordEarthR25Util::stanfordR25ParseTimeslots($allowed);
      if (!is_array($result) || empty($result))
      {
        $msg = 'Invalid format for allowed timeslots.';
        if (is_string($result) and !empty($result)) {
          $msg = $msg . ' ' .$result;
        }
        $form_state->setErrorByName('allowed_timeslots', $this->t($msg));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $location = $this->entity;
    $status = $location->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Location %label created.', [
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
