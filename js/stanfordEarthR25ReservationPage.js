// reservation form tweaks, such as limiting date/time picker
(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.stanfordEarthR25ReservationPage = {
    attach: function (context, setting) {
      Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak();
    }
  };
})(jQuery, Drupal, drupalSettings);
