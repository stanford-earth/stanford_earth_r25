<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Contains Drupal\stanford_earth_r25\Form\StanfordEarthR25CancelForm.
 */
class StanfordEarthR25ModifyForm extends ConfirmFormBase {

  /**
   * The R25 location (room) id.
   *
   * @var string
   */
  protected $locationId;

  /**
   * The op: cancel or confirm.
   *
   * @var string
   */
  protected $op;

  /**
   * The R25 event id.
   *
   * @var string
   */
  protected $eventId;

  /**
   * The event date for dispay purposes.
   *
   * @var string
   */
  protected $start;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_r25_modify';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form,
                            FormStateInterface $form_state,
                            $op = NULL,
                            $locationId = NULL,
                            $eventId = NULL,
                            $start = NULL) {
    $storage = $form_state->getStorage();
    $result = $storage['stanford_earth_r25']['event_info']['output'];
    $rooms = [];
    $room_id = $locationId;

    $this->locationId = $locationId;
    $this->op = $op;
    $this->eventId = $eventId;
    $this->start = $start;

    if (!empty($room_id)) {
      $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room_id);
      $rooms[$room_id] = $config->getRawData();
    }

    $opout = 'cancellation';
    if ($op === 'confirm') {
      $opout = 'confirmation';
    }

    // Get the event title from the XML for display.
    $title = '';
    if (!empty($result['vals'][$result['index']['R25:EVENT_NAME'][0]]['value'])) {
      $title = $result['vals'][$result['index']['R25:EVENT_NAME'][0]]['value'];
    }

    // Find out if this is a recurring event so we can ask whether the operation
    // is for the instance or the entire series.
    $event_count = 0;
    if (!empty($result['index']['R25:RESERVATION_START_DT']) &&
      is_array($result['index']['R25:RESERVATION_START_DT'])
    ) {
      $event_count = count($result['index']['R25:RESERVATION_START_DT']);
    }

    $msg = 'Do you want to ' . $op . ' reservation "';
    if (!empty($title)) {
      $msg .= Html::escape($title);
    }
    else {
      $msg .= $eventId;
    }
    $msg .= '"';
    if (!empty($rooms[$room_id]['label'])) {
      $msg .= ' in room ' . $rooms[$room_id]['label'];
    }
    if (!empty($start)) {
      $startdate = DrupalDateTime::createFromFormat(DATE_W3C, $start);
      $msg .= ' for ' . $startdate->format("l, F j, Y g:i a");
    }
    $msg .= '? <br />';
    $form['room_id'] = [
      '#type' => 'hidden',
      '#value' => $room_id,
    ];
    $form['event_id'] = [
      '#type' => 'hidden',
      '#value' => $eventId,
    ];
    $form['really'] = [
      '#markup' => $this->t("@msg", ['@msg' => $msg]),
    ];

    // If the event is part of a recurring series, display all the dates
    // and let user know confirmation applies to all instances in the series
    // or if operation is cancel, let them choose entire series or occurence.
    if ($event_count > 1) {
      $msg_text = 'This reservation is part of a series. ' . ucfirst($opout) . ' will apply to all dates.<br />';
      if (!empty($result['index']['R25:RESERVATION_START_DT']) &&
        is_array($result['index']['R25:RESERVATION_START_DT'])
      ) {
        $msg_text .= 'Reservation dates: <br/>';
        foreach ($result['index']['R25:RESERVATION_START_DT'] as $value) {
          if (!empty($result['vals'][$value]['value'])) {
            $s_date = DrupalDateTime::createFromFormat(DATE_W3C, $result['vals'][$value]['value']);
            $msg_text .= $s_date->format("l, F j, Y g:i a") . '<br />';
          }
        }
        $msg_text .= '<br />';
      }
      $form['markup2'] = [
        '#markup' => $this->t("@msg_text",
                        ['@msg_text' => $msg_text]),
      ];
      $form['series'] = [
        '#type' => 'radios',
        '#default_value' => 1,
        '#options' => [
          1 => $this->t('Cancel this occurrence'),
          2 => $this->t('Cancel entire series'),
        ],
      ];
    }
    else {
      $form['series'] = [
        '#type' => 'hidden',
        '#value' => 2,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $opout = 'cancellation';
    if ($this->op === 'confirm') {
      $opout = 'confirmation';
    }
    $rooms = [];
    $room_id = $this->locationId;
    if (!empty($room_id)) {
      $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room_id);
      $rooms[$room_id] = $config->getRawData();
    }
    $storage = $form_state->getStorage();
    $xml_string = $storage['stanford_earth_r25']['event_info']['raw-xml'];
    $result = $storage['stanford_earth_r25']['event_info']['output'];

    $find_str = 'status="est"';
    $user_input = $form_state->getUserInput();
    if ($user_input['series'] == 1) {
      $reservation_id = 0;
      if (!empty($result['index']['R25:RESERVATION_START_DT']) &&
        is_array($result['index']['R25:RESERVATION_START_DT'])
      ) {
        foreach ($result['index']['R25:RESERVATION_START_DT'] as $key => $value) {
          if (!empty($result['vals'][$value]['value']) &&
            $this->start == $result['vals'][$value]['value']
          ) {
            $reskey = $result['index']['R25:RESERVATION_ID'][$key];
            $reservation_id = $result['vals'][$reskey]['value'];
            break;
          }
        }
        if ($reservation_id > 0) {
          $resptr1 = strpos($xml_string, '<r25:reservation_id>' . $reservation_id);
          if ($resptr1 !== FALSE) {
            $pos = strpos($xml_string, $find_str);
            if ($pos !== FALSE) {
              $xml_string = substr_replace($xml_string, 'status="mod"', $pos, strlen($find_str));
            }
            $proptr = strpos($xml_string, '<r25:profile');
            if ($proptr !== FALSE) {
              $pos = strpos($xml_string, $find_str, $proptr);
              if ($pos !== FALSE) {
                $xml_string = substr_replace($xml_string, 'status="mod"', $pos, strlen($find_str));
              }
            }
            $temp1 = substr($xml_string, 0, $resptr1);
            $resptr2 = strrpos($temp1, $find_str);
            if ($resptr2 !== FALSE) {
              $xml_string = substr($xml_string, 0, $resptr2) . 'status="mod"' . substr($xml_string, $resptr2 + 12);
            }
            $resptr2 = strpos($xml_string, '<r25:reservation_state>', $resptr1);
            $resptr3 = strpos($xml_string, '</r25:reservation_state>', $resptr1);
            if ($resptr2 !== FALSE && $resptr3 !== FALSE) {
              $xml_string = substr_replace($xml_string, '<r25:reservation_state>99</r25:reservation_state>', $resptr2, $resptr3 + 24 - $resptr2);
            }
          }
        }
      }
    }
    else {
      if ($user_input['series'] == 2) {
        $pos = strpos($xml_string, $find_str);
        if ($pos !== FALSE) {
          $xml_string = substr_replace($xml_string, 'status="mod"', $pos, strlen($find_str));
        }
        $pos1 = strpos($xml_string, '<r25:state>');
        $pos2 = strpos($xml_string, '</r25:state>');
        if ($pos1 !== FALSE && $pos2 !== FALSE) {
          $new_state = ($this->op == 'confirm') ? '2' : '99';
          $xml_string = substr_replace($xml_string, '<r25:state>' . $new_state . '</r25:state>', $pos1, $pos2 + 12 - $pos1);
        }
      }
    }

    // Put the event XML back to 25Live to set the new cancel or confirm state.
    $r25_service = \Drupal::service('stanford_earth_r25.r25_call');
    $r25_service->stanfordR25ApiCall('event-put', $xml_string, $this->eventId);

    // If we want to email room approvers or the user about the confirmation
    // or cancellation, build up the email list and send information.
    if ($rooms[$room_id]['email_cancellations']) {
      $secgroup_id = '';
      if (!empty($rooms[$room_id]['approver_secgroup_id'])) {
        $secgroup_id = $rooms[$room_id]['approver_secgroup_id'];
      }
      $additional = '';
      if (!empty($rooms[$room_id]['email_list'])) {
        $additional = $rooms[$room_id]['email_list'];
      }
      $email_list = StanfordEarthR25Util::stanfordR25BuildEventEmailList($result, $secgroup_id, $additional);
      $user = \Drupal::currentUser();
      // Get the event title from the XML for display.
      $title = '';
      if (!empty($result['vals'][$result['index']['R25:EVENT_NAME'][0]]['value'])) {
        $title = $result['vals'][$result['index']['R25:EVENT_NAME'][0]]['value'];
      }
      $event_count = 0;
      if (!empty($result['index']['R25:RESERVATION_START_DT']) &&
        is_array($result['index']['R25:RESERVATION_START_DT'])
      ) {
        $event_count = count($result['index']['R25:RESERVATION_START_DT']);
      }

      $body = [];
      $body[] = 'A Room Reservation ' . $opout . ' request was sent by ' . $user->getDisplayName();
      $body[] = ' for "' . $title . '" in room ' . $rooms[$room_id]['label'];
      $startdate = DrupalDateTime::createFromFormat(DATE_W3C, $this->start);
      if ($event_count > 1) {
        if ($user_input['series'] == 1) {
          $body[] = ' for the instance on ' . $startdate->format("l, F j, Y g:i a");
        }
        else {
          if ($user_input['series'] == 2) {
            $body[] = ' for the entire series including ' . $startdate->format("l, F j, Y g:i a");
          }
        }
      }
      else {
        $body[] = ' for the reservation starting ' . $startdate->format("l, F j, Y g:i a");
      }
      $subject = 'Room reservation ' . $opout;
      $params = [
        'body' => $body,
        'subject' => $subject,
      ];
      $params = [
        'body' => $body,
        'subject' => $subject,
      ];
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'stanford_earth_r25';
      $key = 'r25_operation';
      $to = $email_list;
      $params['message'] = $body;
      $params['r25_operation'] = $subject;
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;
      $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    }
    // The operation is done, so go back to the calendar page.
    $url = new Url('entity.stanford_earth_r25_location.calendar',
      ['r25_location' => $this->locationId]);
    $form_state->setRedirectUrl($url);
    sleep(3);
  }

  /**
   * Confirm button text.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Button text markup.
   */
  public function getConfirmText() {
    $opStr = $this->op;
    return $this->t('@opstr reservation',
      ['@opstr' => $opStr]);
  }

  /**
   * Cancel or Confirm cancel button text.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Cancel text.
   */
  public function getCancelText() {
    return $this->t('Return to calendar');
  }

  /**
   * Cancel or Confirm reservation question.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Markup for question.
   */
  public function getQuestion() {
    $opStr = ucfirst($this->op);
    return $this->t('@opstr Reservation?',
      ['@opstr' => $opStr]);
  }

  /**
   * Url to return to if user hits cancel.
   *
   * @return \Drupal\Core\Url
   *   the Url.
   */
  public function getCancelUrl() {
    return new Url('entity.stanford_earth_r25_location.calendar',
      ['r25_location' => $this->locationId]);
  }

}
