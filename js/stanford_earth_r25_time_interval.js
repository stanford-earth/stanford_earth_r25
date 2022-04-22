(function ($, Drupal, drupalSettings) {

  'use strict';
  Drupal.behaviors.stanford_earth_r25_time_interval = {
    attach: function (context, settings) {

      $('[id*=edit-stanford-r25-booking].form-time').each(function() {
        $(this).change(function () {
          //$('.form-time').change(function() {
          var newTime = ($(this).val());
          var newHour = newTime.substr(0, 3);
          var newMinute = newTime.substr(3, 2);
          var oldMinuteProp = $(this).prop('oldMinute');
          if (typeof oldMinuteProp === 'undefined') {
            oldMinuteProp = '30';
          }
          if (newMinute !== oldMinuteProp) {
            var oldMinute = parseInt(oldMinuteProp);
            if (isNaN(oldMinute) || oldMinute < 0) {
              oldMinute = 30;
            }
            if (oldMinute > 0 && oldMinute < 30) {
              oldMinute = 30;
            }
            else if (oldMinute > 30) {
              oldMinute = 0;
            }
            var newMinuteStr;
            if (oldMinute === 0) {
              newMinuteStr = '30';
            }
            else {
              newMinuteStr = '00';
            }
            $(this).val(newHour + newMinuteStr);
            $(this).prop('oldMinute', newMinuteStr);
          }
        });
      });
    }
  };
}) (jQuery, Drupal, drupalSettings);
