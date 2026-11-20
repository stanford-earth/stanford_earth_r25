<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\smartsheet\SmartsheetClientInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;

/**
 * Handles incoming Smartsheet webhook requests.
 */
class StanfordEarthR25SmartsheetEndpoint extends ControllerBase {

  /**
   * The logger channel for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The Smartsheet config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The Smartsheet client service.
   *
   * @var \Drupal\smartsheet\SmartsheetClientInterface;
   */
   protected $smartsheetClient;

  /**
   * Page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   *   The kill switch service.
   */
  protected $killSwitch;

  /**
   * Constructs a new SdssSmartsheetEndpoint controller.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *    The config factory.
   * @param \Drupal\smartsheet\SmartsheetClientInterface $smartsheetClient
   *    The smartsheet client service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch;
   *    The kill switch service.
   */
  public function __construct(
    LoggerInterface $logger,
    ConfigFactoryInterface $config_factory,
    SmartsheetClientInterface $smartsheetClient,
    KillSwitch $killSwitch,
  ) {
    $this->logger = $logger;
    $this->config = $config_factory->get('smartsheet.config');
    $this->smartsheetClient = $smartsheetClient;
    $this->killSwitch = $killSwitch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('logger.factory')->get('sdss_smartsheet'),
      $container->get('config.factory'),
      $container->get('smartsheet.client'),
      $container->get('page_cache_kill_switch')
    );
  }

  private function randomCode(int $length = 6): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I to avoid confusion
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
      $out .= $chars[random_int(0, $max)];
    }
    return $out;
  }

  /**
   * Entry point for all Smartsheet webhook POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An HTTP response.
   */
  public function handle(Request $request): Response {
    $payload = $request->getContent();
    $data = json_decode($payload, TRUE);
    $this->killSwitch->trigger();
    // 1. Handle Smartsheet's one-time verification challenge.
    // Smartsheet sends Smartsheet-Hook-Challenge on webhook registration.
    // We must echo it back both as a header and in the JSON body.
    $challenge = $request->headers->get('Smartsheet-Hook-Challenge');
    if ($challenge) {
        $this->logger->info('challenge response');      
        return new JsonResponse(
        ['smartsheetHookResponse' => $challenge],
        Response::HTTP_OK,
        ['Smartsheet-Hook-Response' => $challenge],
      );
    }

    // 2. Verify the HMAC-SHA256 signature.
    // Smartsheet signs every request with the shared secret you provided
    // when registering the webhook.
    $this->logger->info('verifying');
    $signature = $request->headers->get('Smartsheet-Hmac-SHA256', '');
    $xyz = print_r($signature,true);
    $this->logger->info('signature: '.$xyz);
    $expectedSig = hash_hmac('sha256', $payload, "3wqyfyypur6n4xkfonaks7o7jh");
    $xyz = print_r($expectedSig, true);
    $this->logger->info('expected: '.$xyz);
    if (!hash_equals($expectedSig, $signature)) {
      $this->logger->warning('Smartsheet webhook received with invalid HMAC signature.');
      return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
    }
    else {
      $this->logger->info('smartsheet webhook verified!');
    }
    $this->logger('verified');
    // 3. Process events.
    // Note: Smartsheet events only identify *what* changed, not the new
    // values. Fetch fresh data from the Smartsheet API if needed.
    if (!empty($data['events'])) {
      foreach ($data['events'] as $event) {
        $objectType = $event['objectType'] ?? '';  // 'row', 'cell', 'column', etc.
        $eventType  = $event['eventType'] ?? '';   // 'created', 'updated', 'deleted'
        $rowId      = $event['rowId'] ?? NULL;
        $sheetId    = $data['scopeObjectId'] ?? NULL;

        $this->logger->info(
          'Smartsheet event: @type @object (row: @row, sheet: @sheet)',
          [
            '@type'   => $eventType,
            '@object' => $objectType,
            '@row'    => $rowId ?? 'n/a',
            '@sheet'  => $sheetId ?? 'n/a',
          ],
        );

        // Dispatch to dedicated handlers by event type.
        match (TRUE) {
          $objectType === 'row' && $eventType === 'created' => $this->onRowCreated($event, $data),
          $objectType === 'row' && $eventType === 'updated' => $this->onRowUpdated($event, $data),
          $objectType === 'row' && $eventType === 'deleted' => $this->onRowDeleted($event, $data),
          default => NULL,
        };
      }
    }

    // Respond 200 immediately; Smartsheet will retry on non-2xx responses.
    return new Response('OK', Response::HTTP_OK);
  }

  /**
   * Handles a row-created event.
   *
   * @param array $event
   *   The individual event data from Smartsheet.
   * @param array $payload
   *   The full decoded webhook payload.
   */
  protected function onRowCreated(array $event, array $payload): void {
    $this->logger->info('row created!');
    $newRowIds = [];
    foreach ($payload['events'] ?? [] as $event) {
      if (($event['objectType'] ?? '') === 'row' && ($event['eventType'] ?? '') === 'created') {
        $newRowIds[] = $event['id'];
      }
    }
    $rows = [];
    foreach (array_unique($newRowIds) as $rowId) {
      $rows[] = [
        'id'    => $rowId,
        'cells' => [[
          'columnId' => 1717655060647812,
          'value'    => $this::randomCode(),
        ]],
      ];
    }
    $status = $this->smartsheetClient->put('/sheets/2719023612776324/rows',$rows);
    if ($status === FALSE) {
      $rows_out = print_r($rows, TRUE);
      $this->logger->info('Unable to add project code to new row: '.$rows_out);
    }
  }

  /**
   * Handles a row-updated event.
   *
   * @param array $event
   *   The individual event data from Smartsheet.
   * @param array $payload
   *   The full decoded webhook payload.
   */
  protected function onRowUpdated(array $event, array $payload): void {
    $this->logger->info('row updated!');
    // TODO: Implement row-updated logic.
    // Call the Smartsheet API here to fetch the updated row's data if needed.
  }

  /**
   * Handles a row-deleted event.
   *
   * @param array $event
   *   The individual event data from Smartsheet.
   * @param array $payload
   *   The full decoded webhook payload.
   */
  protected function onRowDeleted(array $event, array $payload): void {
    $this->logger->info('row deleted!');
    // TODO: Implement row-deleted logic.
  }

}
