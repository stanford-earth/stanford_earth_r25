<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;
use Drupal\Core\Url;
use Drupal\Core\Routing\LocalRedirectResponse;

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
   */
  public function modify($op, $location_id, $event_id, $start) {
    $event = StanfordEarthR25Util::_stanford_r25_user_can_cancel_or_confirm($location_id,
      $event_id, $op);
    if (!$event) {
      $response = ['#markup' => 'Unable to ' . $op . ' event ' . $event_id];
      //$url = Url::fromRoute('system.403');
      //$response = new LocalRedirectResponse($url->toString());
    } else {
      $form_state = new FormState();
      $form_state->addBuildInfo('args',
        [
          $op,
          $location_id,
          $event_id,
          $start
        ]
      );
      $form_state->setStorage(
        [
          'stanford_earth_r25' =>
            [
              'event_info' => $event
            ]
        ]
      );
      // Get the modal form using the form builder.
      $modify_form = $this->formBuilder->buildForm('Drupal\stanford_earth_r25\Form\StanfordEarthR25ModifyForm', $form_state);
      $response = [$modify_form];
    }
    return $response;
  }

}
