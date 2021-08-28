<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystem;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Contains Drupal\stanford_earth_r25\Form\StanfordEarthR25ReservationForm.
 */
class StanfordEarthR25ReservationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_r25_reservation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $room = NULL) {
    $rooms = [];
    $adminSettings = [];
    if (!empty($room)) {
      $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room);
      $rooms[$room] = $config->getRawData();
      $config = \Drupal::config('stanford_earth_r25.adminsettings');
      $adminSettings = $config->getRawData();
    }
    $form['#prefix'] = '<div id="modal_reservation_form">';
    $form['#suffix'] = '</div>';

    // AJAX messages.
    $form['stanford_r25_ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'stanford-r25-ajax-messages',
      ],
    ];

    // keep the roomid for later processing
    $form['stanford_r25_booking_roomid'] = array(
      '#type' => 'hidden',
      '#value' => $room,
    );

    // if the room only accepts tentative bookings, then put up a message to that effect
    if (!empty($rooms[$room]['status']) && intval($rooms[$room]['status']) == StanfordEarthR25Util::STANFORD_R25_ROOM_STATUS_TENTATIVE ) {
        $form['stanford_r25_booking_tentative'] = array(
          '#type' => 'markup',
          '#markup' => "<p>This room only accepts tentative reservations which must be approved by the room's administrator.</p>",
        );
      }

    // use the Drupal date popup for date and time picking
    $daytime = DrupalDateTime::createFromTimestamp(time());
    $dayParts = [];
    $dayParts['year'] = $daytime->format("Y");
    $dayParts['month'] = $daytime->format("m");
    $dayParts['day'] = $daytime->format("d");
    $dayParts['hour'] = $daytime->format("H");
    $minutes = intval($daytime->format("i"));
    if ($minutes > 0 && $minutes < 30) {
      $minutes = 30;
    }
    else if ($minutes > 30)  {
      $minutes = 0;
      $hour = intval($dayParts['hour']) + 1;
      if ($hour == 24) {
        $hour = 0;
      }
      $dayParts['hour'] = $hour;
    }
    $dayParts['minute'] = $minutes;
    $dayParts['seconds'] = 0;

      $form['stanford_r25_booking_date'] = array(
        '#type' => 'datetime',
        '#default_value' => DrupalDateTime::createFromArray($dayParts),
        '#required' => TRUE,
        '#title' => 'Start Date/Time',
      );

      if (empty($rooms[$room]['multi_day'])) {
        // for non-multi-day rooms. default booking duration is limited to 2 hours
        // in 30 minute increments, but the room config can have a different value.
        // A value of 0 hours is the same as a value of 24 hours.
        $max_hours = 2;
        if (!empty($rooms[$room]['max_hours'])) {
          $max_hours = intval($rooms[$room]['max_hours']);
          if ($max_hours == 0) {
            $max_hours = 24;
          }
        }
        $hours_array = array();
        if ($max_hours > 2) {
          for ($i = 0; $i < $max_hours; $i++) {
            $hstr = '';
            if ($i == 0) {
              $hours_array[] = '30 minutes';
              $hours_array[] = '1 hour';
            }
            else {
              $hours_array[] = strval($i) . '.5 hours';
              $hours_array[] = strval($i + 1) . ' hours';
            }
          }
        }
        else {
          $hours_array[0] = '30 minutes';
          $hours_array[1] = '60 minutes';
          if ($max_hours > 1) {
            $hours_array[2] = '90 minutes';
            $hours_array[3] = '120 minutes';
          }
        }
        $form['stanford_r25_booking_duration'] = array(
          '#type' => 'select',
          '#title' => t('Duration'),
          '#options' => $hours_array,
          '#default_value' => 0,
          '#required' => TRUE,
        );
      }
      else {
        // multi-day rooms have an end date and time instead of duration
        $max_hours = '';
        $form['stanford_r25_booking_enddate'] = array(
          '#type' => 'datetime',
          '#default_value' => DrupalDateTime::createFromArray($dayParts),
          '#required' => TRUE,
          '#title' => 'End Date/Time',
        );
      }
      // max headcount for a room comes from parameter passed to the function
      $form['stanford_r25_booking_headcount'] = array(
        '#type' => 'select',
        '#title' => t('Headcount'),
        '#options' => array(),
        '#required' => TRUE,
      );
      $max_headcount = 5;
      if (!empty($rooms[$room]['location_info']['capacity'])) {
        $max_headcount = $rooms[$room]['location_info']['capacity'];
      }
      // add to the select list for the number of possible headcounts
      for ($i = 1; $i < $max_headcount + 1; $i++) {
        $form['stanford_r25_booking_headcount']['#options'][] = strval($i);
      }
      // every booking needs some reason text
      $form['stanford_r25_booking_reason'] = array(
        '#type' => 'textfield',
        '#title' => t('Reason'),
        '#required' => TRUE,
        '#maxlength' => 40,
      );

      // check for event attribute fields, and build 'em
      // each of these corresponds to a "custom attribute" for events in 25Live
      // and are specified in our room config as an array of attrib ids, field name,
      // and field type
      if (!empty($rooms[$room]['event_attribute_fields'])) {
        foreach ($rooms[$room]['event_attribute_fields'] as $attr_id => $attr_info) {
          switch ($attr_info['type']) {
            case 'S':
              $field_type = 'textfield';
              break;
            case 'B':
              $field_type = 'checkbox';
              break;
            case 'X':
              $field_type = 'textarea';
              break;
            default:
              $field_type = '';
          }
          if (!empty($field_type)) {
            $form['stanford_r25_booking_attr' . $attr_id] = array(
              '#type' => $field_type,
              '#title' => $attr_info['name'],
            );
          }
        }
      }

      // in the room config, we can separately specify a contact field attribute
      if (!empty($rooms[$room]['contact_attr_field'])) {
        foreach ($rooms[$room]['contact_attr_field'] as $attr_id => $attr_info) {
          switch ($attr_info['type']) {
            case 'S':
              $field_type = 'textfield';
              break;
            case 'B':
              $field_type = 'checkbox';
              break;
            case 'X':
              $field_type = 'textarea';
              break;
            default:
              $field_type = '';
          }
          if (!empty($field_type)) {
            $user = \Drupal::currentUser();
            $contact_info = $user->getDisplayName() . "\r\n" .
              $user->getEmail() . "\r\n";
            $form['stanford_r25_contact_' . $attr_id] = array(
              '#type' => $field_type,
              '#title' => $attr_info['name'],
              '#default_value' => $contact_info,
            );
          }
        }
      }

      //TBD
      // if the user making the reservation has authenticated in some external
      // fashion (is not logged in as a Drupal user) then we should have been
      // passed the user's displayname and email address in the function call.
      // save them as hidden fields on the reservation form.
      if (!empty($external_acct) && is_array($external_acct)) {
        if (!empty($external_acct['R25_EXTERNAL_DISPLAYNAME']) &&
          is_string($external_acct['R25_EXTERNAL_DISPLAYNAME'])
        ) {
          $form['external_username'] = array(
            '#type' => 'hidden',
            '#value' => $external_acct['R25_EXTERNAL_DISPLAYNAME'],
          );
        }
        if (!empty($external_acct['R25_EXTERNAL_MAIL']) &&
          is_string($external_acct['R25_EXTERNAL_MAIL'])
        ) {
          $form['external_usermail'] = array(
            '#type' => 'hidden',
            '#value' => $external_acct['R25_EXTERNAL_MAIL'],
          );
        }
      }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reserve'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    // display some reservation instructions if available, replacing
    // the [max_duration] tag with the room's maximum meeting duration.
    // Use the site-wide booking instruction unless the room as an override.
    if (!empty($rooms[$room]['override_booking_instructions']['value'])) {
      $booking_instr = $rooms[$room]['override_booking_instructions'];
    }
    else {
      $booking_instr = [
        'value' => '',
        'format' => null
      ];
      if (!empty($adminSettings['stanford_r25_booking_instructions']['value'])) {
        $booking_instr = $adminSettings['stanford_r25_booking_instructions'];
      }
    }
    if (!empty($booking_instr['value'])) {
      $booking_instr['value'] = str_replace('[max_duration]', $max_hours, $booking_instr['value']);
    }
    $form['r25_instructions'] = array(
      '#type' => 'markup',
      '#markup' => check_markup($booking_instr['value'], $booking_instr['format']),
    );

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $error_list = '<ul>';
      foreach ($form_state->getErrors() as $error_field => $error_value) {
        $error_list .= '<li>' . $error_value->render() . '</li>';
      }
      $error_list .= '</ul>';
      $form_state->clearErrors();
      \Drupal::messenger()->deleteAll();
      $response->addCommand(new HtmlCommand('#stanford-r25-ajax-messages', $error_list));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setErrorByName('stanford_r25_booking_date',
      new TranslatableMarkup('Agent Cooper wants to know what year this is.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $xyz = 1;
    /*
         // as mentioned above, when the user submits a reservation requests, save the date and calendar view to cookies
   $('#stanford-r25-reservation').submit(function (event) {
       var view = $('#calendar').fullCalendar('getView');
       document.cookie = "stanford-r25-view=" + view.name;
       document.cookie = "stanford-r25-date=" + $('#edit-stanford-r25-booking-date-datepicker-popup-0').val();
       return true;
   });

  */

  }

}
