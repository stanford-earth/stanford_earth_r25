<?php

namespace Drupal\stanford_earth_r25;

/**
 * Encapsulates information and utility methods.
 */
class StanfordEarthR25Util {

  /**
   * Parse a blackout date text area into an array of start and end dates.
   *
   * @param string $inStr
   *   Input string from text area.
   */
  public static function parse_blackout_dates($inStr = '') {
      $blackouts = [];
      $blackout_text = trim($inStr);
      if (!empty($blackout_text)) {
        $tmp1 = explode("\n", $blackout_text);
        foreach ($tmp1 as $tmp2) {
          $blackout = trim($tmp2);
          if (preg_match('(\d{4}-\d{2}-\d{2} - \d{4}-\d{2}-\d{2})', $blackout) === 1) {
            $tmp3 = explode(" - ", $blackout);
            $blackouts[] = array('start' => $tmp3[0], 'end' => $tmp3[1]);
          }
        }
      }
      return $blackouts;
  }

}
