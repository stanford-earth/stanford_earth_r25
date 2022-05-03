(function($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.stanfordEarthR25DateTimeTweaks = {};
  Drupal.behaviors.stanfordEarthR25DateTimeTweaks.tweak = function() {
    console.log('this is the inserted test file!');
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
    var time = $('.js-form-item-stanford-r25-booking-date-time .form-time').val();
    if (time !== undefined) {
      $('.js-form-item-stanford-r25-booking-date-time .form-time').val(time.substr(0, 5));
    }
    time = $('.js-form-item-stanford-r25-booking-enddate-time .form-time').val();
    if (time !== undefined) {
      $('.js-form-item-stanford-r25-booking-enddate-time .form-time').val(time.substr(0, 5));
    }
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
  };
})(jQuery, Drupal, drupalSettings);
