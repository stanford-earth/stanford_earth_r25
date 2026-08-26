// reservation form tweaks, such as limiting date/time picker
(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.stanfordEarthR25ReservationPage = {
    attach: function (context, setting) {
      context.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
          const submitBtn = this.querySelector('input[type="submit"], button[type="submit"]');
          if (submitBtn) {
            submitBtn.disabled = true;
          }
        });
      });
      Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak();
    }
  };
})(jQuery, Drupal, drupalSettings);
