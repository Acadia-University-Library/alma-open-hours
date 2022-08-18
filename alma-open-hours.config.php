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

// Alma config
// Refer to https://developers.exlibrisgroup.com/alma/apis/docs/conf/R0VUIC9hbG1hd3MvdjEvY29uZi9saWJyYXJpZXMve2xpYnJhcnlDb2RlfS9vcGVuLWhvdXJz/
$ALMA_API_BASEURL = '';
$ALMA_API_KEY = '';
$ALMA_API_LIBRARY_ID = ''; 
$ALMA_API_QUERY_DAYS = 28; // API querying supports between 1 and 28 days
$ALMA_API_QUERY_DAYS_MULTIPLE = 1; // Retrieve a multiple of API query days between 1 and 13

// UTC to CMS server standard time offset
$UTC_OFFSET_CMS_STANDARD_TIME = 0;

// Format friendly open/close times in the final $hours array
$DATE_TIME_FORMAT = 'D M j/y g:ia';

// Test mode; if true, don't update CMS db
$TEST = true;

// Dump intermedia results; if true, write output to screen; if false, run silent
$DUMP = true;

?>