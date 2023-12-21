# Stanford Earth R25
8.x-1.11
-------------------------------------------------------------------------
-Release Date: 2023-12-20__

- Increase calendar update delay after reservation from 5 to 7.5 seconds.
- Correct default room type display in Location edit form.
- Ensure R25 Scheduler Account email doesn't display for "quick book" account.

8.x-1.10
-------------------------------------------------------------------------
-Release Date: 2023-11-14__

- Add su_button and su_link classes to links in twig template.
_ Fix missing 25Live scheduler id in adminsettings.
_ Fix incorrect link to events within 25Live application.
_ Change 'cancel' to 'Cancel' in modify/cancel form.
_ Change photo width in css to be wider.
_ Change displayName hook in custom sub-module to hook_user_format_name.
_ Trim contact information in Hartley field to test for empty.
_ Removed test submodule for SAML-only users.

8.x-1.9
-------------------------------------------------------------------------
-Release Date: 2023-11-08__

- Update call to jquery .once to Drupal core/once for Drupal 10.x.

8.x-1.8
-------------------------------------------------------------------------
-Release Date: 2023-11-07__

- Change references from ModuleHandler to ModuleHandlerInterface.

8.x-1.7
-------------------------------------------------------------------------
-Release Date: 2023-10-22__

- Add custom twig extension to replace twig_tweak module.
- Remove css and js to properly align calendar entries on earth site.
- Replace deprecated Drupal and Symfony calls with 10.x compatible calls.

8.x-1.6
-------------------------------------------------------------------------
-Release Date: 2023-09-20_

- Export reservation data to csv for dashboard.

8.x-1.5
-------------------------------------------------------------------------
-Release Date: 2023-01-10_

- Allow use of & in reservation titles.

8.x-1.4
-------------------------------------------------------------------------
-Release Date: 2022-10-31_

- Change Hartley email address to sdss-deans-office-reservations@stanford.edu.

8.x-1.3
-------------------------------------------------------------------------
_Release Date: 2022-05-19_

- Add reply-to address to reservation emails.

8.x-1.2
-------------------------------------------------------------------------
_Release Date: 2022-05-05_

- Add functionality to support Hartley conference center.
- Add dev functionality to support non-Drupal saml authenticated users.

8.x-1.1
-------------------------------------------------------------------------
_Release Date: 2021-11-16_

- Drupal 9.x release.

8.x-1.0-alpha7
-------------------------------------------------------------------------
_Release Date: 2021-11-11_

- EARTH-0000 Fix up external js references for D9.

8.x-1.0-alpha6
-------------------------------------------------------------------------
_Release Date: 2021-10-06_

- EARTH-0000 Adds functionality to override view and book permissions by room.

8.x-1.0-alpha5
-------------------------------------------------------------------------
_Release Date: 2021-09-25_

- EARTH-0000 Require composer/installer in composer.json.

8.x-1.0-alpha4
-------------------------------------------------------------------------
_Release Date: 2021-09-15_

- EARTH-0000 Remove external authentication method options.
- EARTH-0000 Contact info fixes and implement hook_alter for contact.
- EARTH-0000 Scroll "please wait" throbber into view on modal dialog.
- EARTH-0000 Remove <br /> from confirm/cancel are-you-sure message.
- EARTH-0000 Fix create location message.

8.x-1.0-alpha3
--------------------------------------------------------------------------------
_Release Date: 2021-09-09_

- Sync up with github tagging.

8.x-1.0-alpha2
--------------------------------------------------------------------------------
_Release Date: 2021-09-09_

- Code cleanup and fix spud calendars.

8.x-1.0-alpha1
--------------------------------------------------------------------------------
_Release Date: 2021-09-01_

- Initial Release
