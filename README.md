# Ex Libris Alma "open-hours" API Endpoint Helper

A basic utility to retrieve a library's hours of service the from Ex Libris Alma `/open-hours` API endpoint; then convert this data into an array of UNIX timestamps which can be used to update the host's website CMS calendar.

Developer information regarding the `/open-hours` API endpoint can be found @ https://developers.exlibrisgroup.com/alma/apis/docs/conf/R0VUIC9hbG1hd3MvdjEvY29uZi9saWJyYXJpZXMve2xpYnJhcnlDb2RlfS9vcGVuLWhvdXJz/.
## Configuration

The following variables can be set in `alma-open-hours.config.php`:

### Required

* `$ALMA_API_BASEURL` = (string) Base API URL of your Alma instance. (e.g.  `https://api-ca.hosted.exlibrisgroup.com/almaws/v1/conf/libraries/`)

* `$ALMA_API_KEY` = (string) Your Alma API key.

* `$ALMA_API_LIBRARY_ID` = (string) Your library ID within Alma. This is probably a short ALL-CAPS abbreviation of your library's name.

### Optional

* `$ALMA_API_QUERY_DAYS` = (integer, range 1-28, default 28) Number of days into the future for which you would like to retrieve data.

* `$ALMA_API_QUERY_DAYS_MULTIPLE` = (integer, range 1-13, default 1) Number of times to execute the query operation. Setting this value greater than 1 allows you to retrieve more than 28 days of data; to a maximum of 364 days.

* `$UTC_OFFSET_CMS_STANDARD_TIME` = (float, default 0) Hours offset between UTC and your CMS server's local standard time. If your CMS calendar doesn't match the times that were originally entered into Alma, use this variable to make an adjustment accordingly.

* `$DATE_TIME_FORMAT` = (string, default "Y-m-d H:i") Friendly format of date/time values contained in the `'open_time'`, `'close_time'`, `'cms_open_time'` and `'cms_close_time'` keys of the final `$hours` array.

* `$TEST` = (boolean, default true) Enable testing mode where the Alma API is polled and data is prepared, but the contents of `alma-open-hours.cms_import.php` are not executed; therefore, your CMS database is not changed. Note: Turning on test mode also turns on data-dump mode, below.

* `$DUMP` = (boolean, default true) Enable data-dump mode. Outputs the API query URL(s), raw JSON, prepared array of times, and status of each step in the data process.

### Caching

To minimize the frequency of API queries, raw JSON is cached in a subdirectory of this utility that is (not suprisingly) named `./cache`. If you wish to use caching, please ensure that this directory can be read from and written to by your web server. Cache files will be named `yyyy-mm-dd.json` where each file corresponds to the first date of a given `/open-hours` API call.

## Importing Open Hours Into Your CMS

Do it yourself! :)

Seriously though, you'll need to write your own CMS/database import code and place it in `alma-open-hours.cms_import.php`.

Prepared data is stored in the `$hours` variable. If you run the open-hours utility in a web browser with the `$TEST` configuration variable set to `true`, you'll see a 2-dimensional associative array that looks something like this:

```
[2022-08-19] => Array
  (
    [iso_date] => 2022-08-19
    [day_of_year] => 230
    [is_closed] => 0
    [has_exceptions] => 0
    [unixts_open_time] => 1660896000
    [unixts_close_time] => 1660928400
    [open_time] => Fri Aug 19/22 8:00am
    [close_time] => Fri Aug 19/22 5:00pm
    [cms_unixts_open_time] => 1660906800
    [cms_unixts_close_time] => 1660939200
    [cms_open_time] => Fri Aug 19/22 11:00am
    [cms_close_time] => Fri Aug 19/22 8:00pm
    [cms_utc_offset] => 3
  )

[2022-08-20] => Array
  (
    [iso_date] => 2022-08-20
    [day_of_year] => 231
    [is_closed] => 1
    [has_exceptions] => 0
    [unixts_open_time] => 1660953600
    [unixts_close_time] => 1661040000
    [open_time] => Sat Aug 20/22 12:00am
    [close_time] => Sun Aug 21/22 12:00am
    [cms_unixts_open_time] => 1660964400
    [cms_unixts_close_time] => 1661050800
    [cms_open_time] => Sat Aug 20/22 3:00am
    [cms_close_time] => Sun Aug 21/22 3:00am
    [cms_utc_offset] => 3
  )
```

### Using the `$hours` Array

Each array element of `$hours` is keyed to a basic ISO-formatted date which corresponds to the calendar date for a given range of open (and closed) hours. Further, stored within each element of `$hours` is a child array which, itself, contains an assortment of potentially useful data elements for populating your own CMS' database.

* `$hours['yyyy-mm-dd']` = (array)
  * `['iso_date']` = (date, format "Y-m-d")
  * `['day_of_year']` = (date, format "z") Ordinal date within the parent element's year.
  * `['is_closed']` = (integer, range 0-1) Numeric boolean representation of whether the library is closed (`1`) or open (`0`) on this date.
  * `['has_exceptions']` = (integer, range 0+) If this date's open hours were comprised of a regular open/close time as well exceptions thereto, the value indicates how many time exceptions were set in Alma.
  * `['unixts_open_time']` = (UNIX timestamp) UTC opening time retrieved from the API query.
  * `['unixts_close_time']` = (UNIX timestamp) UTC closing time retrieved from the API query.
  * `['open_time']` = (date, format per config `$DATE_TIME_FORMAT`) Friendly version of `['unixts_open_time']` for reference purposes.
  * `['close_time']` = (date, format per config `$DATE_TIME_FORMAT`) Friendly version of `['unixts_close_time']` for reference purposes.
  * `['cms_unixts_open_time']` = (UNIX timestamp) Opening time with the server's local offset applied, per optional configuration variable `$UTC_OFFSET_CMS_STANDARD_TIME`.
  * `['cms_unixts_close_time']` = (UNIX timestamp) Closing time with the server's local offset applied, per optional configuration variable `$UTC_OFFSET_CMS_STANDARD_TIME`.
  * `['cms_open_time']` = (date, format per config `$DATE_TIME_FORMAT`) Friendly version of `['cms_unixts_open_time']` for reference purposes.
  * `['cms_close_time']` = (date, format per config `$DATE_TIME_FORMAT`) Friendly version of `['cms_unixts_close_time']` for reference purposes.
  * `['cms_utc_offset']` = (float) Copy of config `$UTC_OFFSET_CMS_STANDARD_TIME` for reference purposes.
  
## License

This utility is licensed under the GNU Public License (GPL) version 3. Refer to [`LICENSE.md`](LICENSE.md) for the complete text.

## Copyright & Contact

Copyright (C) 2022  Vaughan Memorial Library, Acadia University
* https://library.acadiau.ca
* library-systems@acadiau.ca
