// reservation form tweaks, such as limiting date/time picker
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.stanfordEarthR25Reservation = {
    attach: function (context) {
      $(window)
        .once('stanford-earth-r25-reservation')
        .on('dialog:aftercreate', function() {
          Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak();
        });
    }
  };
})(jQuery, Drupal, drupalSettings);
