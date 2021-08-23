(function ($, Drupal, drupalSettings) {

    'use strict';
    // javascript to display calendar and control embeds (spuds) from 25Live Publisher

    // get the webname of the "spud" from Drupal
    Drupal.behaviors.stanford_earth_r25_spud = {
      attach: function (context, settings) {
        if (typeof drupalSettings.stanfordR25Room !== 'undefined') {

          var stanford_r25_room = drupalSettings.stanfordR25Room;
          var stanford_r25_webname = drupalSettings.stanfordR25Spud;

          $Trumba.addSpud({
            webName: stanford_r25_webname,
            spudType: 'chooser',
            spudId: 'control-spud'
          });

          $Trumba.addSpud({
            webName: stanford_r25_webname,
            spudType: 'main',
            spudId: 'calendar-spud'
          });

        }
      }
    };
})(jQuery, Drupal, drupalSettings);
