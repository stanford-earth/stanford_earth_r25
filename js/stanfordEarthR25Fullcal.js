// javascript for managing FullCalendar display and populating the reserve form on calendar time selects

var qtip = false;  // assume we don't have the qtip library to start
var calendar;

(function ($, Drupal, drupalSettings) {

  'use strict';
  Drupal.behaviors.stanfordEarthR25Fullcalendar = {
    attach: function (context, settings) {

      // if we are coming back from a reservation, check cookies for date to bring the user back to
      var defaultDate = readCookie('stanford-r25-date');
      if (defaultDate === null) {
        // if no cookie, see if a date was set by Drupal from a URL parameter
        if (drupalSettings.stanfordEarthR25.hasOwnProperty('stanfordR25ParamDate')) {
          defaultDate = drupalSettings.stanfordEarthR25.stanfordR25ParamDate;
        }
        else {
          // otherwise, just use today's date
          defaultDate = new Date();
        }
      } else {
        defaultDate = new Date(defaultDate);
      }
      // cookie would be a single-use thing, so delete it
      deleteCookie("stanford-r25-date");

      // settings for regular calendar or list view
      var calButtons = 'dayGridMonth,timeGridWeek,timeGridDay';
      var viewPrefix = 'timeGrid';
      if (drupalSettings.stanfordEarthR25.stanfordR25CalType === 3) {
        viewPrefix = "list";
        calButtons = ""; //'listMonth,listWeek,listDay';
      }

      // if we are coming back from  a reservation, check cookies for the calendar view
      var defaultView = readCookie('stanford-r25-view');
      if (defaultView === null) {
        // if no cookie, see if a view was set by Drupal from a URL parameter
        if (drupalSettings.stanfordEarthR25.hasOwnProperty('stanfordR25ParamView')) {
          defaultView = drupalSettings.stanfordEarthR25.stanfordR25ParamView;
        }
        else {
          if (drupalSettings.stanfordEarthR25.stanfordR25CalType === 3) {
            defaultView = 'listMonth';
            //defaultView = 'dayGridMonth';
          } else {
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
      }
      // delete single-use cookie from reservation
      deleteCookie('stanford-r25-view');

      // allow the use of qtip tooltips if available and the user's permissions and the room's settings are appropos.
      if (drupalSettings.stanfordEarthR25.stanfordR25Qtip === 'qtip' &&
        drupalSettings.stanfordEarthR25.stanfordR25Access === 1 &&
        drupalSettings.stanfordEarthR25.stanfordR25Status > 0) {
        qtip = true;
      }

      var calendarEl = document.getElementById('calendar');
      // get the romm id set on the server in Drupal
      var stanford_r25_room = drupalSettings.stanfordEarthR25.stanfordR25Room;
      // get the room status to see if it is enabled
      var stanford_r25_status = drupalSettings.stanfordEarthR25.stanfordR25Status;
      // the calendar is selectable by the user if the room is bookable and the user has access
      var multiDay = false;  // typically do not allow multi-day reservation
      var selectConstraint = {startTime: '06:00', endTime: '22:00'};  // limit selection to "normal" hours
      var selectable = false;  // value of selectable will determine if user can select timeslots from fullcalendar
      if (parseInt(stanford_r25_status) > 1 && parseInt(drupalSettings.stanfordEarthR25.stanfordR25Access) === 1) {
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
      var allowOverlap = false;
      if (selectable && stanford_r25_room.space_id.indexOf("+") > -1) {
        allowOverlap = true;
      }
      var minTimeSlot = stanford_r25_room.slot_min_time;
      if (minTimeSlot == null) {
        minTimeSlot = '00:00:00';
      }
      var maxTimeSlot = stanford_r25_room.slot_max_time;
      if (maxTimeSlot == null) {
        maxTimeSlot = '24:00:00';
      }
      var weekends = true;
      var hide_weekends = stanford_r25_room.hide_weekends;
      if (hide_weekends == 1) {
        weekends = false;
      }
      var setCalendar = true;
      if (typeof(calendar) === 'object') {
        if (calendar instanceof FullCalendar.Calendar) {
          setCalendar = false;
        }
      }
      if (setCalendar) {
        calendar = new FullCalendar.Calendar(calendarEl, {
          schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source',
          allDaySlot: false,
          // if in month view for a non-multi-day room and the user clicks a date, go to agenda day view

          dateClick: function (info) {
            if (info.view.type === 'dayGridMonth' && !multiDay) {
              calendar.gotoDate(info.dateStr);
              calendar.changeView('timeGridDay');
            }
          },
          datesSet: function (viewDate) {
            // when rendering the calendar, add a permalink to the date and view if room config added permalink id
            if ($('#stanford-r25-permalink').length) {
              var permalink = location.origin + location.pathname +
                '?view=' + viewDate.view.type + '&date=' +
                calendar.formatIso(calendar.getDate()).substring(0, 10);
              $('#stanford-r25-permalink').html('<a href="' + permalink + '">Permalink to this page</a>');
            }
            // if there is an upper limit on calendar view, hide (or show) the 'Next' button
            if (calendarLimit < viewDate.end) {
              $("#calendar .fc-next-button").hide();
              return false;
            }
            else {
              $("#calendar .fc-next-button").show();
            }
            var today = new Date();
            if (viewDate.start <= today &&
              drupalSettings.stanfordEarthR25.stanfordR25CalType === 3) {
              $("#calendar .fc-prev-button").hide();
              return false;
            }
            else {
              $("#calendar .fc-prev-button").show();
            }
          },
          dayMaxEventRows: true,
          eventClick: function (eventClickInfo) {
            if (drupalSettings.stanfordEarthR25.stanfordR25CalType === 3) {
              reserveTime(eventClickInfo.event.start,
                eventClickInfo.event.end, multiDay, maxDuration,
                stanford_r25_room, eventClickInfo.event.extendedProps.price);
            }
          },
          eventDidMount: function (info) {
            // fc elements appear to be mis-aligned.
            //var fcTop = parseInt(info.el.parentElement.style.top,10) - 12;
            //info.el.parentElement.style.top = fcTop.toString() + 'px';
            //var fcBottom = parseInt(info.el.parentElement.style.bottom, 10) + 12;
            //info.el.parentElement.style.bottom = fcBottom.toString() + 'px';
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
          eventMouseEnter: function (mouseEnterInfo) {
            $('body').css('cursor', 'pointer');
          },
          eventMouseLeave: function (mouseEnterInfo) {
            $('body').css('cursor', 'default');
          },
          eventSources: [
            {
              url: 'r25_feed',
              method: 'POST',
              extraParams: {
                room_id: stanford_r25_room,
              },
            }
          ],
          headerToolbar: {
            left: 'today prev,next',
            center: 'title',
            //right: 'dayGridMonth,timeGridWeek,timeGridDay'
            right: calButtons
          },
          // set the default date and view, either from our cookies (see above) or for current date and month
          initialDate: defaultDate,
          initialView: defaultView,
          loading: function (bool) {
            var empty = document.getElementsByClassName('fc-list-empty');
            var loading = document.getElementById('stanford-r25-loading');
            if (bool) {
              if (empty.length > 0) {
                empty[0].innerHTML = 'Loading...';
              }
              if (loading !== null) {
                loading.style.display = 'inherit';
              }
              $('body').css('cursor', 'progress');
            }
            else {
              if (empty.length > 0) {
                empty[0].innerHTML = 'No availability found for this time period.';
              }
              if (loading !== null) {
                loading.style.display = 'none';
              }
              $('body').css('cursor', 'default');
            }
          },
          noEventsDidMount: function (obj) {
            var msg = (obj.el.getElementsByClassName('fc-list-empty-cushion'));
            if (msg.length > 0) {
              msg[0].style.display = 'none';
            }
          },
          // when the user clicks and drags to select a date and time, populate the date, time, and duration fields
          // in the reservation form and set the focus to the required headcount field. Also display an error alert
          // if the user tries to select more than the meximum minutes duration
          select: function (selectInfo) {
            var start = selectInfo.start;
            var end = selectInfo.end;
            reserveTime(start, end, multiDay, maxDuration, stanford_r25_room,'');
          },
          // set whether the calendar is selectable, as defined up above
          selectable: selectable,
          selectConstraint: selectConstraint,
          // don't let users select time slots that cross existing reservations
          selectMinDistance: 1,
          selectOverlap: allowOverlap,
          slotMinTime: minTimeSlot,
          slotMaxTime: maxTimeSlot,
          // set default timezone
          timezone: drupalSettings.stanfordEarthR25.stanfordR25Timezone,
          weekends: weekends,
          error: function () {
            $('#stanford-r25-self-serve-msg').html('Unable to retrieve room schedule from 25Live.');
          },
        });
      }
      calendar.render();

      var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function (mutation) {
          if ($('.ajax-progress-throbber').length) {
            $('.ajax-progress-throbber').get(0).scrollIntoView(false);
          }
        });
      });
      var elementToObserve = $('#drupal-modal').get(0);
      observer.observe(elementToObserve, {subtree: true, childList: true});

      $('.js-form-submit').click(function(){
        $(this).stanfordEarthR25ProgressCursor();
        //$('body').css('cursor', 'progress');
      });
    }
  };

  function reserveTime(start, end, multiDay, maxDuration, stanford_r25_room, price) {
    //var start = selectInfo.start;
    //var end = selectInfo.end;
    var exclude = '';
    if (stanford_r25_room.space_id.indexOf("+") > -1) {
      var events = calendar.getEvents();
      for (var i = 0; i < events.length; i++) {
        if (events[i].start < end && events[i].end > start) {
          if (exclude.length > 0) {
            exclude += '-';
          }
          var exclude_space_id = events[i].extendedProps.space_id;
          exclude += exclude_space_id;
          if (stanford_r25_room.multi_room_parents !== null && stanford_r25_room.multi_room_parents !== undefined) {
            if (exclude_space_id in stanford_r25_room.multi_room_parents) {
              var parent = stanford_r25_room.multi_room_parents[exclude_space_id];
              if (exclude.indexOf(parent) < 0) {
                exclude += '-' + parent;
              }
            }
            for (const [key, value] of Object.entries(stanford_r25_room.multi_room_parents)) {
              if (value[0] === exclude_space_id) {
                exclude += '-' + key;
              }
            }
          }
        }
      }
    }
    var endStr = '';
    var okaytosubmit = true;
    // account for multi-day rooms that have an end date/time instead of a duration
    if (multiDay || stanford_r25_room.caltype === "3") {
      var endMonth = parseInt(end.getMonth()) + 1;
      endStr = '-end-' + end.getFullYear() + '-' + endMonth.toString() +
        '-' + end.getDate() + '-' + end.getHours() + '-' +
        end.getMinutes();
    }
    else {
      var duration = (end - start) / 60000;
      if (maxDuration > 0 && duration > maxDuration) {
        var maxStr = '';
        if (maxDuration > 120) {
          maxStr = (maxDuration / 60) + ' hours';
        }
        else {
          maxStr = maxDuration + ' minutes';
        }
        okaytosubmit = false;
        window.alert('Maximum booking duration is ' + maxStr + '. For longer please contact a department administrator.');
      }
      else {
        var durationIndex = (duration / 30) - 1;
        endStr = '-duration-' + durationIndex.toString();
      }
    }
    if (okaytosubmit && exclude.length > 0) {
      var spaces = arguments[4].space_id.split("+");
      var excludes = exclude.split("-");
      var exclude_count = 0;
      for (var i = 0; i < spaces.length; i++) {
        if (excludes.indexOf(spaces[i]) > -1) {
          exclude_count += 1;
        }
      }
      if (exclude_count >= spaces.length) {
        window.alert('No spaces are available for the selected timeslot. Please choose another.');
        okaytosubmit = false;
      }
    }
    if (okaytosubmit) {
      // as mentioned above, when the user submits a reservation requests, save the date and calendar view to cookies
      var view = calendar.view;
      document.cookie = 'stanford-r25-view=' + view.type;
      document.cookie = 'stanford-r25-date=' + start.toString();
      var link = $('#stanford-r25-reservation a').attr('href');
      if (link === undefined || link === null ) {
        link = "/r25/reservation/" + stanford_r25_room.id + "/now";
      }
      var month = parseInt(start.getMonth()) + 1;
      var startStr = start.getFullYear() + '-' + month.toString() + '-' +
        start.getDate() + '-' + start.getHours() + '-' +
        start.getMinutes() + endStr;
      link = link.replace('now', startStr);
      var price_out = '0';
      if (price.length > 0) {
        price_out = price.toString();
      }
      link += '/' + price_out;
      if (exclude.length > 0) {
        link += '/' + exclude;
      }
      if (stanford_r25_room['nopopup_reservation_form'] == 1) {
        window.location.href = link;
      } else {
        var ajaxSettings = {
          url: link,
          dialogType: 'modal',
          dialog: {width: 800},
        };
        var myAjaxObject = Drupal.ajax(ajaxSettings);
        myAjaxObject.execute();
      }
    }
  }

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

  function refetchEvents(source) {
    source.refetch();
  }

  $.fn.stanfordEarthR25Refresh = function() {
    var sources = calendar.getEventSources();
    if (sources.length) {
      setTimeout(refetchEvents, 7500, sources[0]);
    }
  };

  $.fn.stanfordEarthR25ProgressCursor = function() {
    $('body').css('cursor', 'progress');
  };

  $.fn.stanfordEarthR25DefaultCursor = function() {
    $('body').css('cursor', 'default');
  };

  // unused - but keep around just in case
  /*
  $.fn.stanfordEarthR25Message = function(data) {
    $(this).scrollTop(0);
    //alert($(this).html());
  };
  */

}) (jQuery, Drupal, drupalSettings);
