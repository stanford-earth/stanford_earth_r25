<?php

namespace Drupal\stanford_earth_r25\Service;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Session\AccountProxy;
use GuzzleHttp\ClientInterface;

/**
 * Service to interface with CollegeNet R25 (25Live) API.
 */
class StanfordEarthR25Service {

  /**
   * Global config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */

  protected $httpClient;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Mail Manager service.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Global site settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs a StanfordEarthR25Service object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Drupal config data.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Mail\MailManager $mailmgr
   *   The mail manager service.
   * @param \Drupal\Core\Session\AccountProxy $curUser
   *   The current Drupal user.
   */
  public function __construct(ClientInterface $http_client,
                              ConfigFactory $config = NULL,
                              LoggerChannelFactoryInterface $logger_factory,
                              MailManager $mailmgr,
                              AccountProxy $curUser) {
    $this->httpClient = $http_client;
    $this->config = $config->get('stanford_earth_r25.credentialsettings');
    $this->logger = $logger_factory->get('system');
    $this->mailManager = $mailmgr;
    $this->currentUser = $curUser;
    $this->settings = $config->get('system.site');
  }

  /**
   * Queries the R25 API and returns response data.
   *
   * @param string $command
   *   The R25 API command.
   * @param string $post_data
   *   XML data to be posted to the R25 call.
   * @param string $id
   *   An R25 event id.
   *
   * @return array
   *   An array of results data.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function stanfordR25ApiCall(
    string $command = NULL,
    string $post_data = NULL,
    string $id = NULL
  ) {

    $api_result = [
      'status' => [
        'status' => FALSE,
      ],
      'output' => [],
    ];
    try {
      // Get our R25 credential path from config and read it in.
      if ($command == 'test' && !empty($post_data)) {
        $post_data = unserialize($post_data);
        $url = $post_data['base_url'];
        $credential_path = $post_data['credential'];
      }
      else {
        $credential_path = $this->config->get('stanford_r25_credential');
        // See the collegenet API documentation for more information:
        // https://knowledge25.collegenet.com/display/WSW/WebServices+API
        // get the base url for the organization's 25Live back-end.
        $url = $this->config->get('stanford_r25_base_url');
      }
      $credential = '';
      if (!empty($credential_path)) {
        $credential = @file_get_contents($credential_path);
        if (!empty($credential)) {
          $credential = trim($credential);
        }
      }
      // Add the 25Live admin credential to the url and force it to https.
      if (empty($credential) || empty($url)) {
        return $api_result;
      }
      $url = 'https://' . $credential . '@' . substr($url, (strpos($url, '://') + 3));
      // Figure out which 25Live API command corresponds to what we want to do.
      switch ($command) {
        case 'reserve':
          $xml_command = 'events.xml';
          break;

        case 'roominfo':
          $xml_command = 'space.xml?space_id=' . $post_data;
          break;

        case 'roomphoto':
          $xml_command = 'image?image_id=' . $post_data;
          break;

        case 'feed':
          $xml_command = 'rm_reservations.xml?include=related&' . $post_data;
          break;

        case 'delete':
          $xml_command = 'event.xml?event_id=' . $post_data;
          break;

        case 'acctinfo':
          $xml_command = 'contact.xml?current=T';
          break;

        case 'secgroup':
          $xml_command = 'secgroups.xml?group_name=' . $post_data;
          break;

        case 'r25users':
          $xml_command = 'r25users.xml?security_group_id=' . $post_data;
          break;

        case 'evatrb':
          $xml_command = 'evatrb.xml?attribute_id=' . $post_data;
          break;

        case 'event-get':
          $xml_command = 'event.xml?event_id=' . $post_data;
          break;

        case 'event-put':
          $xml_command = 'event.xml?event_id=' . $id;
          break;

        case 'billing-get':
          $xml_command = 'event.xml?event_id=' . $post_data . '&include=billing+history';
          break;

        case 'billing-put':
          $xml_command = 'event.xml?event_id=' . $id . '&include=billing+history';
          break;

        case 'download':
          $xml_command = 'rm_reservations.xml?' . $post_data . '&include=pending+text+attributes';
          break;

        default:
          $xml_command = 'null.xml';
      }
      // Add the webservices command to the url.
      $url = rtrim($url, '/') . '/' . $xml_command;

      // Depending on what we're doing, the HTTP method will be
      // GET, POST, or DELETE.
      $method = 'GET';
      $options = [];
      if ($command == 'reserve') {
        // $post_data contains the XML for a reservation request
        $method = 'POST';
        $options['body'] = $post_data;
      }
      else {
        // Post_data has the XML to update an event or its billing group id.
        if ($command == 'billing-put' || $command == 'event-put') {
          $method = 'PUT';
          $options['body'] = $post_data;
        }
        else {
          if ($command == 'delete') {
            $method = 'DELETE';
          }
        }
      }
      $options['method'] = $method;
      $options['headers'] = [
        'Content-Type' => 'text/xml; charset=UTF-8',
      ];
      $result = $this->httpClient->request($method,
        $url,
        $options);

      // If the request returns an error, report it.
      if ($result->getStatusCode() > 206) {
        $errmsg = 'HTTP Error response from R25 API: ' . $result->getReasonPhrase();
        $this->logger->error($errmsg);
        $api_result['status']['message'] = $errmsg;
        return $api_result;
      }

      // Get the response contents.
      $xmlbody = $result->getBody()->getContents();
      // If getting a room photo, the response body will contain JPEG data.
      if ($command == 'roomphoto') {
        if (empty($xmlbody)) {
          $api_result['status']['message'] = 'R25 image output is empty.';
          $this->logger->error($api_result['status']['message']);
        }
        else {
          $api_result['output'] = $xmlbody;
          $api_result['status']['status'] = TRUE;
        }
        return $api_result;
      }

      // If we have XML output, parse it and create an array to return.
      $vals = [];
      $index = [];
      $p = xml_parser_create();
      xml_parse_into_struct($p, $xmlbody, $vals, $index);
      xml_parser_free($p);

      // For the null command, we just want a non-error response.
      if ($xml_command == 'null.xml') {
        if (!empty($vals[0]['tag']) &&
          $vals[0]['tag'] === 'R25:NULL') {
          $api_result['status']['status'] = TRUE;
        }
        return $api_result;
      }

      // We can now return the XML values.
      $api_result['output'] = [
        'vals' => $vals,
        'index' => $index,
      ];

      // Finally if running commands event-get or billing-get, also
      // return the raw XML to be modified.
      if ($command == 'event-get' || $command == 'billing-get') {
        $api_result['raw-xml'] = $xmlbody;
      }

      // Return a true status.
      $api_result['status']['status'] = TRUE;
      return $api_result;
    }
    catch (\Exception $e) {
      $errmsg = 'Error response from R25 API Service: ' . $e->getMessage();
      $this->logger->error($errmsg);
      $api_result['status']['message'] = $errmsg;
      return $api_result;
    }
  }

}
