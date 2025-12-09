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
    var headcount = document.querySelector('[data-drupal-selector="edit-stanford-r25-booking-headcount"]');
    var pressRedirect = drupalSettings.stanfordEarthR25.pressRedirect;
    var extra = document.querySelector('[id="stanford-r25-booking-extra-msg"]');
    if (location.indexOf('Event') < 0) {
      $(reasonid).text('Reason');
      if (headcount !== null) {
        var headcountid = 'label[for=' + headcount.id + ']';
        $(headcountid).show();
        $(headcount).show();
      }
      $(reasonid).show();
      $(reason).show();
      if (extra !== null) {
        $(extra).hide();
      }
    }
    else {
      $(reasonid).text('Event Name');
      if (headcount !== null && pressRedirect) {
        var headcountid = 'label[for='+headcount.id+']';
        var hc = $(headcount).val();
        if (hc === '' || hc === '0') {
          $(headcount).val('1');
        }
        $(headcount).hide();
        $(headcountid).hide();
        var reasontext = $(reason).val();
        if (reasontext === "") {
          var contact = document.querySelector('[data-drupal-selector="edit-stanford-r25-contact-175"]');
          var tentativeReason = "Tentative event";
          if (contact !== null) {
            var contactStr = $(contact).text().split(/\r?\n/);
            var contactName = contactStr[0];
            if ($.trim(contactName) !== "") {
              tentativeReason = tentativeReason + " for " + contactName;
            }
          }
          $(reason).val(tentativeReason);
        }
        $(reason).hide();
        $(reasonid).hide();
        if (extra !== null) {
          $(extra).text("When you click Reserve, the system will make a tentative reservation and redirect you to a form to add additional information.");
          $(extra).show();
        }
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
