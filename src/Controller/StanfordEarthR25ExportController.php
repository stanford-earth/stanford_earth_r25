<?php

namespace Drupal\stanford_earth_r25\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\stanford_earth_r25\Service\StanfordEarthR25Service;
use Drupal\Core\Extension\ModuleHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Drupal\Core\File\FileSystem;

/**
 * Provides a reservations exporter.
 */
class StanfordEarthR25ExportController extends ControllerBase {

  /**
   * Page cache kill switch.
   *
   * @var Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   *   The kill switch service.
   */
  protected $killSwitch;

  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactory
   *   The config factory service.
   */
  protected $configFactory;

  /**
   * Current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected $user;

  /**
   * Stanford R25 API Service.
   *
   * @var Drupal\stanford_earth_r25\Service\StanfordEarthR25Service
   */
  protected $r25Service;

  /**
   * Drupal ModuleHandler
   *
   * @var Drupal\Core\Extension\ModuleHandler
   *   Modulehandler to call hooks.
   */
  protected $moduleHandler;

  /**
   * Drupal FileSystem
   *
   * @var Drupal\Core\File\FileSystem
   *   FileSystem service.
   */
  protected $fileSystem;

  /**
   * StanfordEarthR25FeedController constructor.
   */
  public function __construct(KillSwitch $killSwitch,
                              ConfigFactory $configFactory,
                              AccountInterface $user,
                              StanfordEarthR25Service $r25Service,
                              ModuleHandler $moduleHandler,
                              FileSystem $fileSystem) {
    $this->killSwitch = $killSwitch;
    $this->configFactory = $configFactory;
    $this->user = $user;
    $this->r25Service = $r25Service;
    $this->moduleHandler = $moduleHandler;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('stanford_earth_r25.r25_call'),
      $container->get('module_handler'),
      $container->get('file_system')
    );
  }

  /**
   * Return an array of the julian date and date string of string in DATE_W3C.
   */
  private function julianDate($datestr) {
    $date = substr($datestr, 0, 10);
    $parts = explode("-", $date);
    return [
      'date' => $date,
      'julian' => gregoriantojd(intval($parts[1]), intval($parts[2]), intval($parts[0])),
    ];
  }

  /**
   * Return the duration in minutes of a booking plus the day of the week.
   */
  private function duration($start, $end) {
    $startdate = new \DateTime();
    $startdate->setTimestamp(strtotime($start));
    if (intval($startdate->format("Hi")) < 830) {
      $startdate->setTime(8, 30);
    }
    $dayOfWeek = $startdate->format("l");
    $enddate = new \DateTime();
    $enddate->setTimestamp(strtotime($end));
    if (intval($enddate->format("Hi")) > 1730) {
      $enddate->setTime(17, 30);
    }
    $duration = $startdate->diff($enddate, true);
    $minutes = ($duration->h * 60) + $duration->i;
    return [
      'duration' => $minutes,
      'dayofweek' => $dayOfWeek,
    ];
  }

  /**
   * Return array of durations for each day in a booking.
   */
  private function parseDates($start, $end) {
    // Get the Julian dates for the start and end date of the booking.
    $startJd = $this->julianDate($start);
    $endJd = $this->julianDate($end);
    // If they are the same, return the duration for the same-day booking.
    if ($startJd['julian'] === $endJd['julian']) {
      $duration = $this->duration($start, $end);
      return [[
        'date' => $startJd['date'],
        'dayofweek' => $duration['dayofweek'],
        'duration' => $duration['duration'],
      ]];
    }
    else {
      // Otherwise get durations for each day in the booking.
      $date = substr($start, 0, 10);
      $firstStart = $start;
      $firstEnd = substr($start,0,11) . "17:30:00" .
        substr($start, 19);
      $duration = $this->duration($firstStart, $firstEnd);
      $results = [[
        'date' => $date,
        'dayofweek' => $duration['dayofweek'],
        'duration' => $duration['duration'],
      ]];
      for ($jd = $startJd['julian']+1; $jd < $endJd['julian']; $jd++) {
        $dateobj = \DateTime::createFromFormat('m/d/Y',
                    jdtogregorian($jd),
                    new \DateTimeZone('America/Los_Angeles'));
        $dateobj->setTime(8,30);
        $midStart = $dateobj->format(DATE_W3C);
        $dateobj->setTime(17,30);
        $midEnd =  $dateobj->format(DATE_W3C);
        $duration = $this->duration($midStart, $midEnd);
        $results[] = [
          'date' => substr($midStart, 0, 10),
          'dayofweek' => $duration['dayofweek'],
          'duration' => $duration['duration'],
        ];
      }
      $date = substr($end, 0, 10);
      $lastStart = substr($end, 0, 11) . "08:30:00" .
        substr($end, 19);
      $lastEnd = $end;
      $duration = $this->duration($lastStart, $lastEnd);
      $results[] = [
        'date' => $date,
        'dayofweek' => $duration['dayofweek'],
        'duration' => $duration['duration'],
      ];
      return $results;
    }
    return [];
  }

  /**
   * Returns a set of 25Live reservations for a location.
   *
   * @param \Drupal\Core\Entity\EntityInterface $r25_location
   *   An entity being edited.
   * @param string $start
   *   Start date.
   * @param string $end
   *   End date.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return array|\Symfony\Component\HttpFoundation\BinaryFileResponse
   *   Drupal page markup array.
   */
  public function export(EntityInterface $r25_location, $start, $end, Request $request) {

    // Format the request to the 25Live API from either POST or GET arrays.
    $earliest = 2399;
    $latest = 0;
    $reservations = [];
    $output = [];
    $location_props = $r25_location->toArray();
    if (!empty($location_props['displaytype']) && intval($location_props['displaytype']) > 0) {
      $location_type = 'Unknown';
      if (!empty($location_props['locationtype']) && intval($location_props['locationtype']) > 0) {
        switch (intval($location_props['locationtype'])) {
          case 1:
            $location_type = "Meeting";
            break;
          case 2:
            $location_type = "Lab/Seminar";
            break;
          case 3:
            $location_type = "Event";
            break;
          case 4:
            $location_type = "Vehicle";
            break;
        }
      }
      $room_id = $r25_location->get('id');
      $room_label = $r25_location->get('label');
      $start_date = new \DateTime($start, new \DateTimeZone('America/Los_Angeles'));
      $start_date_out = $start_date->format(DATE_W3C);
      $end = date("Y-m-t", strtotime($end));
      $end = date("Y-m-t H:i", strtotime($end) + ((60*23)+59)*60);
      $end_date = new \DateTime($end, new \DateTimeZone('America/Los_Angeles'));
      $end_date_out = $end_date->format(DATE_W3C);
      $newRequest = new Request([
        'start' => $start_date_out,
        'end' => $end_date_out
      ]);
      $feedController = new StanfordEarthR25FeedController(
        $this->killSwitch,
        $this->configFactory,
        $this->user,
        $this->r25Service,
        $this->moduleHandler);
      $json = $feedController->feed($r25_location, $newRequest);
      $reservations = json_decode($json->getContent(), TRUE);
      if (!empty($reservations)) {
        $filename = $this->fileSystem->tempnam('temporary://', 'bookings_' . $room_id . '_');
        $filename .= '.csv';
        $row_array = [
          'Location',
          'Label',
          'Type',
          'DayOfWeek',
          'Date',
          'Duration',
          'Headcount',
          'Frontend',
        ];
        $fp = fopen($filename, 'w');
        fputcsv($fp, $row_array);
        foreach ($reservations as $reservation) {
          $resDates = $this->parseDates($reservation['start'],
            $reservation['end']);
          foreach ($resDates as $resDate) {
            $row_array = [];
            $row_array['id'] = $room_id;
            $row_array['label'] = $room_label;
            $row_array['type'] = $location_type;
            $row_array['dayofweek'] = $resDate['dayofweek'];
            $row_array['date'] = $resDate['date'];
            $row_array['duration'] = $resDate['duration'];
            $row_array['headcount'] = $reservation['headcount'];
            $frontend = 'Unknown';
            if (!empty($reservation['description']) &&
              strpos($reservation['description'], "Self service") !== FALSE) {
              $frontend = 'Intranet';
            }
            else {
              if (!empty($reservation['scheduled_by']) &&
                strpos($reservation['scheduled_by'], "scheduled in 25Live") != FALSE) {
                $frontend = '25Live';
              }
            }
            $row_array['frontend'] = $frontend;
            fputcsv($fp, $row_array, ',', '"');
          }
        }
        fclose($fp);
        $response = new BinaryFileResponse($filename, 200);
        $response->headers->set('Content_type', 'application/excel');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.substr($filename,12).'"');
        $response->deleteFileAfterSend();
        return $response;
      }
      else {
        $this->killSwitch->trigger();
        return [
          '#markup' => $r25_location->label() . ' is not currently available.',
        ];
      }
    }
    else {
      $this->killSwitch->trigger();
      return [
        '#markup' => $r25_location->label() . ' is not currently available.',
      ];
    }
  }
}
