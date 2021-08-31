<?php

namespace Drupal\stanford_earth_r25\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\stanford_earth_r25\StanfordEarthR25Util;

/**
 * Provides a reservation cancel page.
 */
class StanfordEarthR25CancelController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The StanfordEarthR25CancelController constructor.
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
  public function cancel($location_id, $event_id, $start) {
    $event = StanfordEarthR25Util::_stanford_r25_user_can_cancel_or_confirm($location_id,
      $event_id, 'cancel');
    if (!event) {
      $url = Url::fromRoute('system.403');
      $response = new RedirectResponse($url->toString());
    } else {
      $response = new AjaxResponse();
      // Get the modal form using the form builder.
      $modal_form = $this->formBuilder->getForm('Drupal\stanford_earth_r25\Form\StanfordEarthR25CancelForm', $location_id, $event_id, $start, $event);
      // Add an AJAX command to open a modal dialog with the form as the content.
      $response->addCommand(new OpenModalDialogCommand('Cancel Reservation', $modal_form, ['width' => '800']));
    }
    return $response;
  }

}
