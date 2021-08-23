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
        var selectConstraint = {start: '06:00', end: '22:00'};  // limit selection to "normal" hours
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
      var dValue = drupalSettings.stanfordEarthR25.stanfordR25MaxHours;
      if (!isNaN(dValue) && parseInt(Number(dValue)) === dValue &&
        !isNaN(parseInt(dValue, 10)) && parseInt(dValue, 10) > -1) {
        maxDuration = parseInt(dValue) * 60;
      }
      else {
        selectable = false;
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
        // set the default date and view, either from our cookies (see above) or for current date and month
        initialDate: defaultDate,
        initialView: defaultView,
        dayMaxEventRows: true,
         eventDidMount: function(info) {
            if (qtip) {
              var tooltip = new Tooltip(info.el, {
                title: info.event.extendedProps.tip,
                placement: 'right-start',
                trigger: 'click',
                container: 'body',
                html: true,
              });
            }
         },

          headerToolbar: {
            left: 'today prev,next',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
          },
        events: {
          url: 'r25_feed',
          type: 'POST',
          data: {
            room_id: stanford_r25_room,
          },
        },
          loading: function (bool) {
            if (bool) {
              $('body').css('cursor', 'progress');
            }
            else {
              $('body').css('cursor', 'default');
            }
          },
          error: function () {
            $('#stanford-r25-self-serve-msg').html('Unable to retrieve room schedule from 25Live.');
          },

        });
        calendar.render();
    }
  };

  $(document).on("click", calendar.calendarEl, function (e) {
    $('.tooltip').each(function(){
      $(this).remove();
    });
  });

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
