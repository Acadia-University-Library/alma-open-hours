<?php
/*******************************************************************************

Utility to retrieve library hours of operation from Ex Libris Alma web API.

Copyright (C) 2023  Vaughan Memorial Library, Acadia University
                    https://library.acadiau.ca, library-systems@acadiau.ca

********************************************************************************

This program is free software: you can redistribute it and/or modify it under 
the terms of the GNU General Public License as published by the Free Software 
Foundation, either version 3 of the License, or (at your option) any later 
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY 
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A 
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with 
this program.  If not, see <https://www.gnu.org/licenses/>.

*******************************************************************************/

// PHP config: UTC as default timezone if not overidden in local config file
date_default_timezone_set('UTC');

// Alma config
// Refer to https://developers.exlibrisgroup.com/alma/apis/docs/conf/R0VUIC9hbG1hd3MvdjEvY29uZi9saWJyYXJpZXMve2xpYnJhcnlDb2RlfS9vcGVuLWhvdXJz/
$ALMA_API_BASEURL = '';
$ALMA_API_KEY = '';
$ALMA_API_LIBRARY_ID = ''; 
$ALMA_API_QUERY_DAYS = 28; // API querying supports between 1 and 28 days
$ALMA_API_QUERY_DAYS_MULTIPLE = 1; // Retrieve a multiple of API query days between 1 and 13

// Number of hours after which cached API query data will expire; "0" disables cache
$CACHE_EXPIRY_HOURS = 48;

// Format friendly open/close times in the final $hours array
$DATE_TIME_FORMAT = 'Y-m-d H:i';

// Test mode; if true, don't update CMS db
$TEST = true;

// Dump intermedia results; if true, write output to screen; if false, run silent
$DUMP = true;

// Override default configuration
(include_once('alma-open-hours.config.php')) or die('FATAL ERROR: Missing configuration file.');

// Config validation

if(empty($ALMA_API_BASEURL) || empty($ALMA_API_KEY) || empty($ALMA_API_LIBRARY_ID)) {
  die('FATAL ERROR: One, or more, of API base URL, key and library ID is missing from the configuration.');
}

$ALMA_API_URL = $ALMA_API_BASEURL . $ALMA_API_LIBRARY_ID . '/open-hours?apikey=' . $ALMA_API_KEY . '&format=json';

$ALMA_API_QUERY_DAYS = round($ALMA_API_QUERY_DAYS);
if($ALMA_API_QUERY_DAYS < 1) { $ALMA_API_QUERY_DAYS = 1; }
else if($ALMA_API_QUERY_DAYS > 28) { $ALMA_API_QUERY_DAYS = 28; }

$ALMA_API_QUERY_DAYS_MULTIPLE = round($ALMA_API_QUERY_DAYS_MULTIPLE);
if($ALMA_API_QUERY_DAYS_MULTIPLE < 1) { $ALMA_API_QUERY_DAYS_MULTIPLE = 1; }
else if($ALMA_API_QUERY_DAYS_MULTIPLE > 13) { $ALMA_API_QUERY_DAYS_MULTIPLE = 13; }

if(!is_bool($TEST)) { $TEST = true; }

if(!is_bool($DUMP)) { $DUMP = true; }

if($TEST) { $DUMP = true; }

define('SECONDS_IN_A_DAY', 86400);

$now_ts = time();
$raw = array();

for($k = 0; $k < $ALMA_API_QUERY_DAYS_MULTIPLE; $k++) {
  $offset_ts = ($k * $ALMA_API_QUERY_DAYS * SECONDS_IN_A_DAY);
  $ymd_from = date('Y-m-d', $now_ts + $offset_ts - SECONDS_IN_A_DAY);
  $ymd_to = date('Y-m-d', $now_ts + $offset_ts + ($ALMA_API_QUERY_DAYS * SECONDS_IN_A_DAY));
  $alma_api_url_hours_from_to = $ALMA_API_URL . '&from=' . $ymd_from . '&to=' . $ymd_to;

  dump(
    $alma_api_url_hours_from_to, 
    'Alma Hours API - URL for days ' 
    . ($k * $ALMA_API_QUERY_DAYS + 1) . ' (' . date('M j/y', $now_ts + $offset_ts) . ')'
    . ' to ' 
    . (($k + 1) * $ALMA_API_QUERY_DAYS) . ' (' . date('M j/y', $now_ts + $offset_ts + ($ALMA_API_QUERY_DAYS * SECONDS_IN_A_DAY) - SECONDS_IN_A_DAY) . ')'
  );

  $f_cache = 'cache/' . $ymd_from . '-to-' . $ymd_to . '-hours.json'; 
  if(file_exists($f_cache) && (filemtime($f_cache) > ($now_ts - ($CACHE_EXPIRY_HOURS * 60 * 60)))) {
    $x = file_get_contents($f_cache);
  }
  else {
    $x = file_get_contents($alma_api_url_hours_from_to);
    file_put_contents($f_cache, $x);
  }

  $x = json_decode($x);
  $raw = array_merge($raw, array_slice($x->day, 1, $ALMA_API_QUERY_DAYS, true));
}

dump($raw, 'Alma Hours API - Raw Data');

$hours = array();

for($k = 0; $k < count($raw); $k++) {
  $date = str_replace('Z', '', $raw[$k]->date);
  $date_ts = strtotime($date);
  // Closed
  if(empty($raw[$k]->hour)) {
    $hours[$date] = array(
      'date' => $date,
      'is_closed' => 1,
      'has_exceptions' => 0,
      'open_ts' => $date_ts,
      'close_ts' => $date_ts + SECONDS_IN_A_DAY - 1,
      'open_dt' => date($DATE_TIME_FORMAT, $date_ts),
      'close_dt' => date($DATE_TIME_FORMAT, $date_ts + SECONDS_IN_A_DAY - 1)
    );
  }
  // Open
  else {
    $open_ts = 0;
    $close_ts = 0;
    for($j = 0; $j < count($raw[$k]->hour); $j++) {
      $open_ts_excep = date_time_to_unixts($raw[$k]->date, $raw[$k]->hour[$j]->from);
      if($open_ts == 0 || $open_ts_excep < $open_ts) {
        $open_ts = $open_ts_excep;
      }
      $close_ts_excep = date_time_to_unixts($raw[$k]->date, $raw[$k]->hour[$j]->to);
      if($close_ts == 0 || $close_ts_excep > $close_ts) {
        $close_ts = $close_ts_excep;
      }
    }
    if($open_ts > 0 && $close_ts > 0) {
      if($close_ts < $open_ts) { // After midnight, advance one day
        $close_ts = $close_ts + SECONDS_IN_A_DAY;
      }
      $hours[$date] = array(
        'date' => $date,
        'is_closed' => 0,
        'has_exceptions' => $j-1,
        'open_ts' => $open_ts, 
        'close_ts' => $close_ts, 
        'open_dt' => date($DATE_TIME_FORMAT, $open_ts),
        'close_dt' => date($DATE_TIME_FORMAT, $close_ts)
      );
    }
  }
}
ksort($hours);

dump($hours, 'Alma Hours - ' . count($hours) . ' days ready for CMS import');


// Test mode enabled; bail out before CMS database update happens.
if($TEST) {
  dump($TEST, 'Test Complete (CMS calendar was not updated)');
}
// Otherwise, update the CMS database
else {
  (include_once('alma-open-hours.cms_import.php')) or die('FATAL ERROR: Missing CMS import script.');
}


/******************************************************************************/


function date_time_to_unixts($date, $time) {
  preg_match_all('/[0-9]+/', $date, $date_parts);
  preg_match_all('/[0-9]+/', $time, $time_parts);
  $unixts = mktime($time_parts[0][0], $time_parts[0][1], 0, $date_parts[0][1], $date_parts[0][2], $date_parts[0][0]);
  return $unixts;
}


function unixts_time_to_date($unixts_time) {
  return $unixts_time - ($unixts_time % (24*60*60));
}


function dump($x, $label = '') {
  GLOBAL $DUMP;
  if($DUMP) {
    echo "<pre style=\"color:#444;background-color:#fff;padding:1em;\">\n";
    if(!empty($label)) {
      echo "<strong style=\"color:#b00;\">================================================================================\n";
      echo "    " . strtoupper($label) . "    \n";
      echo "================================================================================</strong>\n\n";
    }
    echo print_r($x, true);
    echo "\n\n</pre>";
    return true;
  }
  return false;
}

?>
