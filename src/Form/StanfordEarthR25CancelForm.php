<?php

namespace Drupal\stanford_earth_r25\Form;

use Drupal\Core\Ajax\CloseModalDialogCommand;
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
use Drupal\Core\Ajax\InvokeCommand;
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
    return 'stanford_earth_r25_cancel';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $room = NULL, $event_id,
                            $start = NULL, $event_data = NULL) {

    $result = $event_data;
    $rooms = [];
    $adminSettings = [];
    if (!empty($room)) {
      $config = \Drupal::config('stanford_earth_r25.stanford_earth_r25.' . $room);
      $rooms[$room] = $config->getRawData();
      $config = \Drupal::config('stanford_earth_r25.adminsettings');
      $adminSettings = $config->getRawData();
    }
    $form['#prefix'] = '<div id="modal_cancel_form">';
    $form['#suffix'] = '</div>';

    // AJAX messages.
    $form['stanford_r25_ajax_messages'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'stanford-r25-ajax-messages',
      ],
    ];

    // get the event title from the XML for display
    $title = '';
    if (!empty($result['vals'][$result['index']['R25:EVENT_NAME'][0]]['value'])) {
      $title = $result['vals'][$result['index']['R25:EVENT_NAME'][0]]['value'];
    }

    // find out if this is a recurring event so we can ask whether the operation
    // is for the instance or the entire series.
    $event_count = 0;
    if (!empty($result['index']['R25:RESERVATION_START_DT']) &&
      is_array($result['index']['R25:RESERVATION_START_DT'])
    ) {
      $event_count = count($result['index']['R25:RESERVATION_START_DT']);
    }

    $msg = 'Do you want to cancel reservation "';
    if (!empty($title)) {
      $msg .= check_plain($title);
    }
    else {
      $msg .= $event_id;
    }
    $msg .= '"';
    if (!empty($rooms[$room_id]['display_name'])) {
      $msg .= ' in room ' . $rooms[$room_id]['display_name'];
    }
    if (!empty($start)) {
      $startdate = DrupalDateTime::createFromFormat(DATE_W3C, $start);
      $msg .= ' for ' . $startdate->format("l, F j, Y g:i a");
    }
    $msg .= '? <br />';
    $form['room_id'] = array(
      '#type' => 'hidden',
      '#value' => $room_id,
    );
    $form['event_id'] = array(
      '#type' => 'hidden',
      '#value' => $event_id,
    );
    $form['really'] = array(
      '#markup' => t($msg),
    );

    // if the event is part of a recurring series, display all the dates
    // and let user know confirmation applies to all instances in the series
    // or if operation is cancel, let them choose entire series or occurence.
    if ($event_count > 1) {
      $msg_text = 'This reservation is part of a series. Cancellation will apply to all dates.<br />';
      if (!empty($result['index']['R25:RESERVATION_START_DT']) &&
        is_array($result['index']['R25:RESERVATION_START_DT'])
      ) {
        $msg_text .= 'Reservation dates: <br/>';
        foreach ($result['index']['R25:RESERVATION_START_DT'] as $key => $value) {
          if (!empty($result['vals'][$value]['value'])) {
            $s_date = DrupalDateTime::createFromFormat(DATE_W3C, $result['vals'][$value]['value']);
            $msg_text .= $s_date->format("l, F j, Y g:i a") . '<br />';
          }
        }
        $msg_text .= '<br />';
      }
      $form['markup2'] = array(
        '#markup' => t($msg_text),
      );
      $form['series'] = array(
        '#type' => 'radios',
        '#default_value' => 1,
        '#options' => array(
          1 => 'Cancel this occurrence',
          2 => 'Cancel entire series'
        ),
      );
    }
    else {
      $form['series'] = array(
        '#type' => 'hidden',
        '#value' => 2,
      );
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel Reservation'),
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

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $r25_messages = [];
    if (!empty($storage['stanford_earth_r25']['r25_messages'])) {
      $r25_messages = $storage['stanford_earth_r25']['r25_messages'];
    }
    $form_state->setStorage([]);

    $response = new AjaxResponse();
    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $error_list = '<ul style="color:red">';
      foreach ($form_state->getErrors() as $error_field => $error_value) {
        $error_list .= '<li>' . $error_value->render() . '</li>';
      }
      $error_list .= '</ul>';
      $form_state->clearErrors();
      \Drupal::messenger()->deleteAll();
      $response->addCommand(new HtmlCommand('#stanford-r25-ajax-messages', $error_list));
      $response->addCommand(new InvokeCommand('#drupal-modal', 'scrollTop', [0]));
    }
    else if (!empty($r25_messages))
    {
      if (count($r25_messages) == 1) {
        $msg_list = '<span style="color:red">' . $r25_messages[0] . '</span>';
      }
      else {
        $msg_list = '<ul style="color:red">';
        foreach ($r25_messages as $r25_message) {
          $msg_list .= '<li>' . $r25_message . '</li>';
        }
        $msg_list .= '</ul>';
      }
      $msg = new TranslatableMarkup($msg_list);
      $response->addCommand(new OpenModalDialogCommand("Booking Result", $msg->render(), ['width' => 800]));
      $response->addCommand(new InvokeCommand(null, 'stanfordEarthR25Refresh'));
    }
    else {
      $response->addCommand(new CloseModalDialogCommand());
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

}

