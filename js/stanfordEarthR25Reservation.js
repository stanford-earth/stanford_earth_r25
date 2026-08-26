// reservation form tweaks, such as limiting date/time picker
//(function ($, Drupal, drupalSettings) {
//  Drupal.behaviors.stanfordEarthR25Reservation = {
//    attach: function (context) {
//      if (!once('stanford-r25-reservation', 'html').length) {
//        return;
//      }
//      $(window)
//        .on('dialog:aftercreate', function() {
//          Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak();
//        });
//    }
//  };
//})(jQuery, Drupal, drupalSettings);
// mymodule/js/prevent-double-submit.js

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.preventDoubleSubmit = {
    attach: function (context, settings) {
      // Target submit buttons inside modals
      once('prevent-double-submit', '.form-submit', context)
        .forEach(function (button) {
          button.addEventListener('click', function (e) {
            const btn = this;

            if (btn.dataset.clicked) {
              e.preventDefault();
              return false;
            }

            btn.dataset.clicked = 'true';
            btn.disabled = true;
            btn.value = btn.value + '...';

            // Re-enable after timeout (fallback for validation failures)
            //setTimeout(function () {
            //  console.log('timeout set');
            //  btn.disabled = false;
            //  btn.value = btn.value.replace('...', '');
            //  delete btn.dataset.clicked;
            //}, 5000);
          });
        });
      $(window)
        .on('dialog:aftercreate', function() {
          Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak();
        });
    }
  };

})(jQuery, Drupal, once);
