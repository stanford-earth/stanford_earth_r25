// javascript for managing FullCalendar display and populating the reserve form on calendar time selects

var qtip = false;  // assume we don't have the qtip library to start

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.stanford_earth_r25_fullcalendar = {
    attach: function (context, settings) {

      // if we are coming back from a reservation, check cookies for date to bring the user back to
      var defaultDate = readCookie("stanford-r25-date");
      if (defaultDate === null) {
        // if no cookie, see if a date was set by Drupal from a URL parameter
        if (drupalSettings.stanfordEarthR25.hasOwnProperty('stanfordR25ParamDate')) {
          defaultDate = drupalSettings.stanfordEarthR25.stanfordR25ParamDate;
        }
        else {
          // otherwise, just use today's date
          defaultDate = new Date();
        }
      }
      // cookie would be a single-use thing, so delete it
      deleteCookie("stanford-r25-date");

      // if we are coming back from  a reservation, check cookies for the calendar view
      var defaultView = readCookie('stanford-r25-view');
      if (defaultView === null) {
        // if no cookie, see if a view was set by Drupal from a URL parameter
        if (drupalSettings.stanfordEarthR25.hasOwnProperty('stanfordR25ParamView')) {
          defaultView = drupalSettings.stanfordEarthR25.stanfordR25ParamView;
        }
        else {
          // otherwise, use the Default view set by Drupal for this room
          switch (drupalSettings.stanfordEarthR25.stanfordR25DefaultView) {
            case '1':
              defaultView = 'timeGridDay';
              break;
            case '2':
              defaultView = 'timeGridWeek';
              break;
            case '3':
              defaultView = 'dayGridMonth';
              break;
            default:
              // finally, default to month view if no other choice
              defaultView = 'dayGridMonth';
          }
        }
      }
      // delete single-use cookie from reservation
      deleteCookie('stanford-r25-view');

      // allow the use of qtip tooltips if available and the user's permissions and the room's settings are appropos.
      if (drupalSettings.stanfordEarthR25.stanfordR25Qtip === 'qtip' &&
        drupalSettings.stanfordEarthR25.stanfordR25Access === 1 &&
        drupalSettings.stanfordEarthR25.stanfordR25Status > 0) {
        qtip = true;
      }

      // get the romm id set on the server in Drupal
      var stanford_r25_room = drupalSettings.stanfordR25Room;


      var calendarEl = document.getElementById('calendar');
      // get the romm id set on the server in Drupal
      var stanford_r25_room = drupalSettings.stanfordEarthR25.stanfordR25Room;
      // get the room status to see if it is enabled
      var stanford_r25_status = drupalSettings.stanfordEarthR25.stanfordR25Status;
      // the calendar is selectable by the user if the room is bookable and the user has access
      var multiDay = false;  // typically do not allow multi-day reservation
      var selectConstraint = {startTime: '06:00', endTime: '22:00'};  // limit selection to "normal" hours
      var selectable = false;  // value of selectable will determine if user can select timeslots from fullcalendar
      if (parseInt(stanford_r25_status) > 1 && parseInt(drupalSettings.stanfordEarthR25.stanfordR25Access) == 1) {
        // in this case, the room is reservable and the user has access to reserve it
        selectable = true;
        if (parseInt(drupalSettings.stanfordEarthR25.stanfordR25MultiDay) === 1) {
          // for multi-day rooms, remove the hour constraint
          multiDay = true;
          selectConstraint = {};
        }
      }
      // some rooms constrain how far into the future a user can reserve.
      var calendarLimit = new Date(parseInt(drupalSettings.stanfordEarthR25.stanfordR25CalendarLimitYear),
        parseInt(drupalSettings.stanfordEarthR25.stanfordR25CalendarLimitMonth));
      // get the maximum selectable duration of the room
      var maxDuration = 0;
      if (selectable) {
        var dValue = parseInt(drupalSettings.stanfordEarthR25.stanfordR25MaxHours);
        if (isNaN(dValue) || dValue < 0) {
          selectable = false;
        }
        else {
          maxDuration = dValue * 60;
        }
      }

      var calendar = new FullCalendar.Calendar(calendarEl, {
          schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
        allDaySlot: false,
        // if in month view for a non-multi-day room and the user clicks a date, go to agenda day view

        dateClick: function(info) {
          if (info.view.type === 'dayGridMonth' && !multiDay) {
            calendar.gotoDate(info.dateStr);
            calendar.changeView('timeGridDay');
          }
        },
        datesSet: function(viewDate) {
          // when rendering the calendar, add a permalink to the date and view if room config added permalink id
          if ($('#stanford-r25-permalink').length) {
            var permalink = location.origin + location.pathname +
              '?view=' + viewDate.view.type + '&date=' +
              calendar.formatIso(calendar.getDate()).substring(0,10);
            $('#stanford-r25-permalink').html('<a href="' + permalink + '">Permalink to this page</a>');
          }
          // if there is an upper limit on calendar view, hide (or show) the 'Next' button
          if ( calendarLimit < viewDate.end) {
            $("#calendar .fc-next-button").hide();
            return false;
          }
          else {
            $("#calendar .fc-next-button").show();
          }
        },
        dayMaxEventRows: true,
        eventDidMount: function(info) {
            if (qtip) {
              var tooltip = new tippy(info.el, {
                allowHTML: true,
                appendTo: calendarEl,
                arrow: true,
                content: info.event.extendedProps.tip,
                interactive: true,
                placement: 'right',
                theme: 'stanford-earth-r25',
                trigger: 'click',
              });
            }
         },
        events: {
          url: 'r25_feed',
          type: 'POST',
          data: {
            room_id: stanford_r25_room,
          },
        },
        headerToolbar: {
            left: 'today prev,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        // set the default date and view, either from our cookies (see above) or for current date and month
        initialDate: defaultDate,
        initialView: defaultView,
        loading: function (bool) {
            if (bool) {
              $('body').css('cursor', 'progress');
            }
            else {
              $('body').css('cursor', 'default');
            }},
        // when the user clicks and drags to select a date and time, populate the date, time, and duration fields
        // in the reservation form and set the focus to the required headcount field. Also display an error alert
        // if the user tries to select more than the meximum minutes duration
        select: function (selectInfo) {
            var start = selectInfo.start;
            var end = selectInfo.end;
            var endStr = '';
            //$('#edit-stanford-r25-booking-date-datepicker-popup-0').val(start.format('YYYY-MM-DD'));
            //$('#edit-stanford-r25-booking-date-timeEntry-popup-1').val(start.format('hh:mm a'));
            // account for multi-day rooms that have an end date/time instead of a duration
            if (multiDay) {
              var endMonth = parseInt(end.getMonth()) + 1;
              endStr = '-end-' + end.getFullYear() + '-' + endMonth.toString() +
                '-' + end.getDate() + '-' + end.getHours() + '-' +
                end.getMinutes();

              //$('#edit-stanford-r25-booking-enddate-datepicker-popup-0').val(end.format('YYYY-MM-DD'));
              //$('#edit-stanford-r25-booking-enddate-timeEntry-popup-1').val(end.format('hh:mm a'));
            } else {
              var duration = (end - start) / 60000;
              if (maxDuration > 0 && duration > maxDuration) {
                var maxStr = '';
                if (maxDuration > 120) {
                  maxStr = (maxDuration / 60) + ' hours';
                } else {
                  maxStr = maxDuration + ' minutes';
                }
                window.alert('Maximum booking duration is ' + maxStr + '. For longer please contact a department administrator.');
              } else {
                var durationIndex = (duration / 30) - 1;
                endStr = '-duration-' + durationIndex.toString();
                //$('#edit-stanford-r25-booking-duration').val(duration_index);
              }
            }
            var link = $('#stanford-r25-reservation a').attr('href');
            var month = parseInt(start.getMonth()) + 1;
            var startStr = start.getFullYear() + '-' + month.toString() + '-' +
              start.getDate() + '-' + start.getHours() + '-' +
              start.getMinutes() + endStr;
            link = link.replace('now',startStr);
            var ajaxSettings = {
              url: link,
              dialogType: 'modal',
              dialog: { width: 800 },
            };
            var myAjaxObject = Drupal.ajax(ajaxSettings);
            myAjaxObject.execute();
        },
        // set whether the calendar is selectable, as defined up above
        selectable: selectable,
        selectConstraint: selectConstraint,
        // don't let users select time slots that cross existing reservations
        selectOverlap: false,
        // set default timezone
        timezone: drupalSettings.stanfordEarthR25.stanfordR25Timezone,
        error: function () {
            $('#stanford-r25-self-serve-msg').html('Unable to retrieve room schedule from 25Live.');
          },
        });
        calendar.render();

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

  // read a javascript cookie
  function readCookie(name)
  {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) === ' ') {
        c = c.substring(1, c.length);
      }
      if (c.indexOf(nameEQ) === 0) {
        return c.substring(nameEQ.length, c.length);
      }
    }
    return null;
  }

  // delete a javascript cookie
  function deleteCookie(name) {
    document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
  }

}) (jQuery, Drupal, drupalSettings);
