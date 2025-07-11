(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.stanford_earth_hartley = {
    attach: function (context, settings) {
      if (drupalSettings.stanfordEarthR25.stanfordR25Room.rules_category == 'press_building') {
        var selector = document.querySelector('[data-drupal-selector="edit-stanford-r25-booking-spaceid"]');
        var reason = document.querySelector('[data-drupal-selector="edit-stanford-r25-booking-reason"]');
        if (selector !== null && reason !== null) {
          roomSelection(selector, reason);
          $(selector).on("change",
            function () {
              roomSelection(selector, reason);
          });
        }
      }
    }
  };

  function roomSelection(selector, reason) {
    var location = (selector.options[selector.selectedIndex].text);
    var reasonid = 'label[for='+reason.id+']';
    if (location.indexOf('Event') < 0) {
      $(reasonid).text('Reason');
    }
    else {
      $(reasonid).text('Event Name');
    }
  }

})(jQuery, Drupal, drupalSettings);
