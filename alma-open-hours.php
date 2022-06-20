<?php
/*******************************************************************************

Utility to retrieve library hours of operation from Ex Libris Alma web API.

Copyright (C) 2022  Vaughan Memorial Library, Acadia University
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

// PHP config
date_timezone_set('UTC');

// Alma config
// Refer to https://developers.exlibrisgroup.com/alma/apis/docs/conf/R0VUIC9hbG1hd3MvdjEvY29uZi9saWJyYXJpZXMve2xpYnJhcnlDb2RlfS9vcGVuLWhvdXJz/
$ALMA_API_BASEURL = 'https://api-ca.hosted.exlibrisgroup.com/almaws/v1/conf/libraries/';
$ALMA_API_KEY = 'MY_API_KEY';
$ALMA_API_LIBRARY_ID = 'MY_LIBRARAY_ID'; 
$ALMA_API_QUERY_DAYS = 28; // API querying supports between 1 and 28 days
$ALMA_API_QUERY_DAYS_MULTIPLE = 1; // Retrieve a multiple of API query days between 1 and 13

// UTC to CMS server standard time offset
$UTC_OFFSET_CMS_STANDARD_TIME = 0;

// Test mode; if true, don't update CMS db
$TEST = true;

// Dump intermedia results; if true, write output to screen; if false, run silent
$DUMP = true;

// Override default configuration
(include_once('alma_hours.config.php')) or die ('ERROR: Missing configuration file.');

$ALMA_API_URL = $ALMA_API_BASEURL . $ALMA_API_LIBRARY_ID . '/open-hours?apikey=' . $ALMA_API_KEY . '&format=json';

if($ALMA_API_QUERY_DAYS < 1) { $ALMA_API_QUERY_DAYS = 1; }
else if($ALMA_API_QUERY_DAYS > 28) { $ALMA_API_QUERY_DAYS = 28; }

if($ALMA_API_QUERY_DAYS_MULTIPLE < 1) { $ALMA_API_QUERY_DAYS_MULTIPLE = 1; }
else if($ALMA_API_QUERY_DAYS_MULTIPLE > 13) { $ALMA_API_QUERY_DAYS_MULTIPLE = 13; }

$unixts_now = time();
$xd = array();

for($k = 0; $k < $ALMA_API_QUERY_DAYS_MULTIPLE; $k++) {
  $unixts_offset = ($k * $ALMA_API_QUERY_DAYS * 86400);
  $ymd_from = date('Y-m-d', $unixts_now + $unixts_offset);
  $ymd_to = date('Y-m-d', ($unixts_now + $unixts_offset + ($ALMA_API_QUERY_DAYS * 86400) - 86400));
  $alma_api_url_hours_from_to = $ALMA_API_URL . '&from=' . $ymd_from . '&to=' . $ymd_to;

  dump($alma_api_url_hours_from_to, 'Alma Hours API - URL for days ' . (1 + $k * $ALMA_API_QUERY_DAYS) . ' (' . $ymd_from . ') to ' . (($k + 1) * $ALMA_API_QUERY_DAYS) . ' (' . $ymd_to . ')');

  $f_cache = $ymd_to . '-hours.json'; 
  if(file_exists($f_cache)) {
    $x = file_get_contents($f_cache);
  }
  else {
    $x = file_get_contents($alma_api_url_hours_from_to);
    file_put_contents($f_cache, $x);
  }

  $x = json_decode($x);
  $xd = array_merge($xd, $x->day);
}

dump($xd, 'Alma Hours API - Raw Data');

$hours = array();

//$ALMA_ADD_EXCEPTIONS = false;

// Convert raw API data to a format that's more conducive to later CMS import
for($k = 0; $k < count($xd); $k++) {
  $unixts_open = 0;
  $unixts_closed = 0;
  for($j = 0; $j < count($xd[$k]->hour); $j++) {
    $unixts_open_excep = date_time_to_unixts($xd[$k]->date, $xd[$k]->hour[$j]->from);
    if($unixts_open == 0 || $unixts_open_excep < $unixts_open) {
      $unixts_open = $unixts_open_excep;
    }
    $unixts_closed_excep = date_time_to_unixts($xd[$k]->date, $xd[$k]->hour[$j]->to);
    if($unixts_closed == 0 || $unixts_closed_excep > $unixts_closed) {
      $unixts_closed = $unixts_closed_excep;
    }
  }
  $iso_date = str_replace('Z', '', $xd[$k]->date);
  if($unixts_open > 0 && $unixts_closed > 0) {
    if($unixts_closed < $unixts_open) {
      $unixts_closed = $unixts_closed + 86400;
    }
    $hours[$iso_date] = array(
      'iso_date' => $iso_date,
      'day_of_year' => date('z', $unixts_open),
      'is_closed' => 0,
      'has_exceptions' => $j-1,
      'unixts_open_time' => $unixts_open, 
      'unixts_close_time' => $unixts_closed 
    );
  }
}
ksort($hours);

// Fill in closed dates
$t_a = unixts_time_to_date($unixts_now);
$t_b = $t_a + ($ALMA_API_QUERY_DAYS * $ALMA_API_QUERY_DAYS_MULTIPLE * 86400);
for($t = $t_a; $t < $t_b; $t += (86400)) {
  $i = date('Y-m-d', $t);
  if(!array_key_exists($i, $hours)) {
    $hours[$i] = array(
      'iso_date' => $i,
      'day_of_year' => date('z', $t),
      'is_closed' => 1,
      'has_exceptions' => 0,
      'unixts_open_time' => $t,
      'unixts_close_time' => $t + 86400
    );
  }
}
ksort($hours);

// Adjust UTC to account for local CMS standard and daylight-saving times.
$daylight_this_year = strtotime('Second Sunday Of March this year');
$standard_this_year = strtotime('First Sunday Of November this year');
$daylight_next_year = strtotime('Second Sunday Of March next year');
$standard_next_year = strtotime('First Sunday Of November next year');
$utc_to_daylight = ($UTC_OFFSET_CMS_STANDARD_TIME - 1) * 3600;
$utc_to_standard = $UTC_OFFSET_CMS_STANDARD_TIME * 3600;
foreach($hours as $hKey => $hValue) {
  if($UTC_OFFSET_CMS_STANDARD_TIME == 0) {
    $hours[$hKey]['cms_unixts_open_time'] = $hValue['unixts_open_time'];
    $hours[$hKey]['cms_unixts_close_time'] = $hValue['unixts_close_time'];
  }
  else {
    $isots = strtotime($hValue['date']);
    // Daylight savings time
    if(($isots >= $daylight_this_year && $isots < $standard_this_year) ||
       ($isots >= $daylight_next_year && $isots < $standard_next_year)) {
      $hours[$hKey]['cms_unixts_open_time'] = $hValue['unixts_open_time'] + $utc_to_daylight;
      $hours[$hKey]['cms_unixts_close_time'] = $hValue['unixts_close_time'] + $utc_to_daylight;
    }
    // Standard time
    else {
      $hours[$hKey]['cms_unixts_open_time'] = $hValue['unixts_open_time'] + $utc_to_standard;
      $hours[$hKey]['cms_unixts_close_time'] = $hValue['unixts_close_time'] + $utc_to_standard;
    }
  }
  $hours[$hKey]['cms_open_time'] = date('D M j/y g:ia', $hours[$hKey]['cms_unixts_open_time']);
  $hours[$hKey]['cms_close_time'] = date('D M j/y g:ia', $hours[$hKey]['cms_unixts_close_time']);
}


dump($hours, 'Alma Hours - ' . count($hours) . ' days ready for CMS import');


// Test mode enabled; bail out before CMS database update happens.
if($TEST) {
  dump($TEST, 'Test Complete (CMS calendar was not updated)');
}
// Otherwise, update the CMS database
else {
  (include_once('alma_hours.cms_import.php')) or die('ERROR: Missing CMS import script.');
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