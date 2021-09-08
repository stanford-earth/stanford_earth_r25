<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Form\FormState;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

/**
 * Provides a reservation cancel/modify page.
 */
class StanfordEarthR25ModifyController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The StanfordEarthR25ModifyController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Returns a calendar page render array.
   *
   * @return array
   *   Page markup.
   */
  public function modify($op, $location_id, $event_id, $start) {
    $event = StanfordEarthR25Util::stanfordR25UserCanCancelOrConfirm($location_id,
      $event_id, $op);
    if (!$event) {
      $response = ['#markup' => 'Unable to ' . $op . ' event ' . $event_id];
    }
    else {
      $form_state = new FormState();
      $form_state->addBuildInfo('args',
        [
          $op,
          $location_id,
          $event_id,
          $start,
        ]
      );
      $form_state->setStorage(
        [
          'stanford_earth_r25' =>
            [
              'event_info' => $event,
            ],
        ]
      );
      // Get the modal form using the form builder.
      $modify_form = $this->formBuilder->buildForm('Drupal\stanford_earth_r25\Form\StanfordEarthR25ModifyForm', $form_state);
      $response = [$modify_form];
    }
    return $response;
  }

}
