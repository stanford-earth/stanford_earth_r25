// reservation form tweaks, such as limiting date/time picker
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.stanfordEarthR25Reservation = {
    attach: function (context) {
      if (!once('stanford-earth-r25-reservation', 'html').length) {
        return;
      }
      $(window)
        .on('dialog:aftercreate', function() {
          Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak();
        });
    }
  };
})(jQuery, Drupal, drupalSettings);
