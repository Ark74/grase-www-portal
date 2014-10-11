<?php

/* Copyright 2008 Timothy White */

/*  This file is part of GRASE Hotspot.

    http://grasehotspot.org/

    GRASE Hotspot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    GRASE Hotspot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with GRASE Hotspot.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once __DIR__ . '/../../../vendor/autoload.php';

$Radmin = new \Grase\Database\Database('/etc/grase/radmin.conf');
$Settings = new \Grase\Database\Radmin($Radmin);

/* PHP No longer correctly gets the timezone from the system. Try to set it */

$tzfile = trim(file_get_contents('/etc/timezone'));

if ($tzfile) {
    date_default_timezone_set($tzfile);
} // TODO Need to catch error here?
else {
    date_default_timezone_set(@date_default_timezone_get());
}
