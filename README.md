# Ex Libris Alma "open-hours" API Endpoint Helper

A basic utility to retrieve a library's hours of service the from Ex Libris Alma `/open-hours` API endpoint; then convert this data into an array of UNIX timestamps which can be used to update the host's website CMS calendar.

Developer information regarding the `/open-hours` API endpoint can be found @ https://developers.exlibrisgroup.com/alma/apis/docs/conf/R0VUIC9hbG1hd3MvdjEvY29uZi9saWJyYXJpZXMve2xpYnJhcnlDb2RlfS9vcGVuLWhvdXJz/.
## Configuration

The following variables can be set in `alma-open-hours.config.php`:

### Required

* `$ALMA_API_BASEURL` = (string) Base API URL of your Alma instance. (e.g. `https://api-ca.hosted.exlibrisgroup.com/almaws/v1/conf/libraries/`)

* `$ALMA_API_KEY` = (string) Your Alma API key.

* `$ALMA_API_LIBRARY_ID` = (string) Your library ID within Alma. This is probably a short ALL-CAPS abbreviation of your library's name.

### Optional

* `date_default_timezone_set('local_timezone')` = (string, default "UTC") Your local timezone. Refer to [PHP's documentation](https://www.php.net/manual/en/function.date-default-timezone-set.php) for more information about this function.

* `$ALMA_API_QUERY_DAYS` = (integer, range 1-28, default 28) Number of days into the future for which you would like to retrieve data.

* `$ALMA_API_QUERY_DAYS_MULTIPLE` = (integer, range 1-13, default 1) Number of times to execute the query operation. Setting this value greater than 1 allows you to retrieve more than 28 days of data; to a maximum of 364 days.

* `$DATE_TIME_FORMAT` = (string, default "Y-m-d H:i") Friendly format of date/time values contained in the `'open_dt'` and `'close_dt'` keys of the final `$hours` array.

* `$TEST` = (boolean, default true) Enable testing mode where the Alma API is polled and data is prepared, but the contents of `alma-open-hours.cms_import.php` are not executed; therefore, your CMS database is not changed. Note: Turning on test mode also turns on data-dump mode, below.

* `$DUMP` = (boolean, default true) Enable data-dump mode. Outputs the API query URL(s), raw JSON, prepared array of hours, and statuses of each step in the data process.

### Caching

To minimize the frequency of API queries, raw JSON is cached in a subdirectory of this utility that is (not suprisingly) named `./cache`. If you wish to use caching, please ensure that this directory can be read from and written to by your web server. Cache files will be named `yyyy-mm-dd.json` where each file corresponds to the first date of a given `/open-hours` API call.

## Importing Open Hours Into Your CMS

Do it yourself! :)

Seriously though, you'll need to write your own CMS/database import code and place it in `alma-open-hours.cms_import.php`.

Prepared data is stored in the `$hours` variable. If you run the open-hours utility in a web browser with the `$TEST` and `$DUMP` configuration variables set to `true`, you'll see a 2-dimensional associative array that looks something like this:

```
[2023-04-28] => Array
  (
    [date] => 2023-04-28
    [is_closed] => 0
    [has_exceptions] => 0
    [open_ts] => 1682679600
    [close_ts] => 1682712000
    [open_dt] => 2023-04-28 08:00
    [close_dt] => 2023-04-28 17:00
  )
[2023-04-29] => Array
  (
    [date] => 2023-04-29
    [is_closed] => 1
    [has_exceptions] => 0
    [open_ts] => 1682737200
    [close_ts] => 1682823599
    [open_dt] => 2023-04-29 00:00
    [close_dt] => 2023-04-29 23:59
  )
```

### Using the `$hours` Array

Each array element of `$hours` is keyed to a basic ISO-formatted date which corresponds to the calendar date for a given range of open (and closed) hours. Further, stored within each element of `$hours` is a child array which, itself, contains an assortment of potentially useful data elements for populating your own CMS database.

* `$hours['yyyy-mm-dd']` = (array)
  * `['date']` = (date, format "yyyy-mm-dd")
  * `['is_closed']` = (integer, range 0-1) Numeric boolean representation of whether the library is closed (`1`) or open (`0`) on this date.
  * `['has_exceptions']` = (integer, range 0+) If this date's open hours are comprised of a regular open/close time as well exceptions thereto, the value indicates how many time exceptions were set in Alma.
  * `['open_ts']` = (UNIX timestamp) Opening time retrieved from the API query. Note: If `['is_closed']` is `1`, then the "opening" time defaults to `00:00:00` (on `['date']`).
  * `['close_ts']` = (UNIX timestamp) Closing time retrieved from the API query. Note: If `['is_closed']` is `1`, then the "closing" time defaults to `23:59:59` (on `['date']`).
  * `['open_dt']` = (datetime, format per config `$DATE_TIME_FORMAT`) Human-readable version of `['open_dt']`.
  * `['close_dt']` = (datetime, format per config `$DATE_TIME_FORMAT`) Human-readable version of `['close_dt']`.

## License

This utility is licensed under the GNU Public License (GPL) version 3. Refer to [`LICENSE.md`](LICENSE.md) for the complete text.

## Source Code

This utility's PHP source code is hosted on [GitHub](https://github.com) @ https://github.com/Acadia-University-Library/alma-open-hours.

## Copyright & Contact

Copyright (C) 2023  Vaughan Memorial Library, Acadia University
* https://library.acadiau.ca
* library-systems@acadiau.ca
