// reservation form tweaks, such as limiting date/time picker
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.stanford_earth_r25_reservation = {
    attach: function (context) {
      $(window)
        .once('stanford-earth-r25-reservation')
        .on('dialog:aftercreate', function() {
          var today = new Date();
          var dd = String(today.getDate()).padStart(2, '0');
          var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
          var yyyy = String(today.getFullYear());
          $('.form-date').attr('min', yyyy + '-' + mm + '-' + dd);
          var month = drupalSettings.stanfordEarthR25.stanfordR25CalendarLimitMonth;
          var day = drupalSettings.stanfordEarthR25.stanfordR25CalendarLimitDay;
          var year = drupalSettings.stanfordEarthR25.stanfordR25CalendarLimitYear;
          $('.form-date').attr('max', year.toString() + '-' + month.toString() + '-' + day.toString());
          $('.form-time').attr('step', '1800');
          var time = $('.form-time').val();
          $('.form-time').val(time.substr(0,5));
        });
    }
  };
})(jQuery, Drupal, drupalSettings);
