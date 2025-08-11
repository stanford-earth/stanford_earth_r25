<?php

namespace Drupal\stanford_earth_hartley\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Builds the form to delete a Location.
 */
class StanfordEarthFarmExportForm extends FormBase {

  public function getFormId() {
    return 'stanford_earth_farm_export_form';
  }

  /**
   * {@inheritdoc }
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $start_date = new DrupalDateTime();
    $start_date->modify('first day of this month');
    $end_date = new DrupalDateTime();
    $end_date->modify('last day of this month');

    $form['farm_export_start_date'] = [
      '#type' => 'datetime',
      '#date_time_element' => 'none',
      '#date_date_element' => 'date',
      '#default_value' => $start_date,
      '#required' => TRUE,
      '#title' => 'Start Date',
    ];
    $form['farm_export_end_date'] = [
      '#type' => 'datetime',
      '#date_time_element' => 'none',
      '#date_date_element' => 'date',
      '#default_value' => $end_date,
      '#required' => TRUE,
      '#title' => 'End Date',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => t('Back to Calendar'),
      '#url' => new Url('entity.stanford_earth_r25_location.calendar',
        ['r25_location' => 'of00']),
      '#attributes' => [
        'class' => [
          'button',
        ],
        'data-drupal-selector' => 'edit-cancel',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $start = $form_state->getValue('farm_export_start_date')->format('Y-m-d');
    $end = $form_state->getValue('farm_export_end_date')->format('Y-m-d');
    $form_state->setRedirectUrl(new Url('stanford_earth_r25_booking.export',
      ['r25_location'=>'of00', 'start'=>$start, 'end'=>$end, 'extended'=>'extended']
    ));
    //$form_state->setRedirectUrl(new Url('entity.stanford_earth_r25_location.calendar',['r25_location' => 'of00']));
  }

}
