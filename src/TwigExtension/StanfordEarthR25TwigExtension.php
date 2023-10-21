<?php

namespace Drupal\stanford_earth_r25\TwigExtension;

use Drupal\Core\Config\ConfigFactory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * StanfordEarthR25TwigExtension provides drupal_config function.
 */
class StanfordEarthR25TwigExtension extends AbstractExtension {

  /**
   * Config factory.
   *
   * @var Drupal\Core\Config\ConfigFactory
   *   The config factory service.
   */
  protected $configFactory;

  /**
   * StanfordEarthR25TwigExtension constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory
   *   The config factory service object.
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * @return \Twig\TwigFunction[]
   *   TwigFunction array.
   */
  public function getFunctions() {
    return [
      new TwigFunction('stanford_earth_r25_config_val',
        [$this, 'stanfordEarthR25ConfigVal']
      ),
    ];
  }

  /**
   * Retrieves data from a given configuration object.
   *
   * @param string $name
   *   The name of the configuration object to construct.
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *
   * @return mixed
   *   The data that was requested.
   */
  public function stanfordEarthR25ConfigVal(string $name, string $key) {
    return $this->configFactory->getEditable($name)->get($key);
  }

}
