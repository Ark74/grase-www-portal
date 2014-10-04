<?php

/* Copyright 2010 Timothy White */

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

$PAGE = 'createtickets';
require_once 'includes/pageaccess.inc.php';
require_once 'includes/session.inc.php';
require_once 'includes/misc_functions.inc.php';

function validate_form()
{
    $error = array();

    $NumberTickets = clean_int($_POST['numberoftickets']);

    $MaxMb = clean_number($_POST['MaxMb']);
    $Max_Mb = clean_number($_POST['Max_Mb']);
    $MaxTime = clean_int($_POST['MaxTime']);
    $Max_Time = clean_int($_POST['Max_Time']);

    $error[] = validate_int($NumberTickets);
    $error[] = validate_datalimit($MaxMb);
    $error[] = validate_datalimit($Max_Mb);
    $error[] = validate_timelimit($MaxTime);
    $error[] = validate_timelimit($Max_Time);
    if ((is_numeric($Max_Mb) || $_POST['Max_Mb'] == 'inherit') && is_numeric($MaxMb)) {
        $error[] = T_("Only set one Data limit field");
    }
    if ((is_numeric($Max_Time) || $_POST['Max_Time'] == 'inherit') && is_numeric($MaxTime)) {
        $error[] = T_("Only set one Time limit field");
    }

    /*
     * TODO: Remove this limit as we now store batches in a new table
     * (Remove once we have a nice easy way to manage batches, accidentally
     * creating 1000 users and not being able to delete them is a bad thing)
     */
    if ($NumberTickets > 50) {
        $error[] = T_("Max of 50 tickets per batch");
    }

    $error[] = validate_group("", $_POST['Group']);
    return array_filter($error);
}


/* ** Process batches actions (delete,print, export) **   */

if (isset($_POST['batchesprint'])) {
    $selectedBatches = array();
    foreach ($_POST['selectedbatches'] as $batch) {
        $selectedBatches[] = clean_number($batch);
    }
    $selectedBatches = implode(',', $selectedBatches);
    if (sizeof($selectedBatches) == 0) {
        $error[] = T_("Please select a batch to print");
        $templateEngine->assign("error", $error);
    } else {
        header(
            "Location: http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) .
            "/printnewtickets?batch=$selectedBatches"
        );
        exit;
    }

}

if (isset($_POST['batchesexport'])) {
    $selectedBatches = array();
    foreach ($_POST['selectedbatches'] as $batch) {
        $selectedBatches[] = clean_number($batch);
    }
    $selectedBatches = implode(',', $selectedBatches);
    if (sizeof($selectedBatches) == 0) {
        $error[] = T_("Please select a batch to export");
        $templateEngine->assign("error", $error);
    } else {
        header(
            "Location: http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) .
            "/exporttickets?batch=$selectedBatches"
        );
        exit;
    }

}

// TODO Delete batches
if (isset($_POST['batchesdelete'])) {
    $selectedBatches = array();
    foreach ($_POST['selectedbatches'] as $batch) {
        $selectedBatches[] = clean_number($batch);
    }
    #$selectedbatches = implode(',', $selectedbatches);
    if (sizeof($selectedBatches) == 0) {
        $error[] = T_("Please select a batch to delete");
        $templateEngine->assign("error", $error);
    } else {
        $users = array();
        foreach ($selectedBatches as $batch) {
            $fetchUsers = DatabaseFunctions::getInstance()->getMultipleUsersDetails($Settings->getBatch($batch));
            if (!is_array($fetchUsers)) {
                $fetchUsers = array();
            }
            $users = array_merge($users, $fetchUsers);
        }
        foreach ($users as $user) {
            print "Deleting " . $user['Username'];
            // TODO Actually delete user
            // Maybe delete user from batch as we go to ensure if we fail
            // at any point the batch is correct?
        }
        // TODO Delete batch from settings
    }
}

/*  **  Process creation of batches **   */

if (isset($_POST['createticketssubmit'])) {
    $error = validate_form();
    if ($error) {
        $user['numberoftickets'] = clean_int($_POST['numberoftickets']);
        $user['MaxMb'] = \Grase\Locale::localeNumberFormat(clean_number($_POST['MaxMb']));
        $user['Max_Mb'] = \Grase\Locale::localeNumberFormat(clean_number($_POST['Max_Mb']));
        if ($_POST['Max_Mb'] == 'inherit') {
            $user['Max_Mb'] = 'inherit';
        }

        $user['MaxTime'] = \Grase\Locale::localeNumberFormat(clean_int($_POST['MaxTime']));
        $user['Max_Time'] = \Grase\Locale::localeNumberFormat(clean_int($_POST['Max_Time']));
        if ($_POST['Max_Time'] == 'inherit') {
            $user['Max_Time'] = 'inherit';
        }

        $user['Group'] = \Grase\Clean::text($_POST['Group']);
        $user['Expiration'] = expiry_for_group(\Grase\Clean::text($_POST['Group']));
        $user['Comment'] = \Grase\Clean::text($_POST['Comment']);
        $templateEngine->assign("user", $user);
        $templateEngine->assign("error", $error);
        $templateEngine->displayPage('newtickets.tpl');
        exit();
    } else {
        $group = \Grase\Clean::text($_POST['Group']);
        // Load group settings so we can use Expiry, MaxMb and MaxTime
        $groupSettings = $Settings->getGroup($group);

        $user['numberoftickets'] = clean_int($_POST['numberoftickets']);

        // TODO: Create function to make these the same across all locations
        if (is_numeric(clean_number($_POST['Max_Mb']))) {
            $MaxMb = clean_number($_POST['Max_Mb']);
        }
        if (is_numeric(clean_number($_POST['MaxMb']))) {
            $MaxMb = clean_number($_POST['MaxMb']);
        }
        if ($_POST['Max_Mb'] == 'inherit') {
            $MaxMb = @ $groupSettings[$group]['MaxMb'];
        }

        if (is_numeric(clean_int($_POST['Max_Time']))) {
            $MaxTime = clean_int($_POST['Max_Time']);
        }
        if (is_numeric(clean_number($_POST['MaxTime']))) {
            $MaxTime = clean_int($_POST['MaxTime']);
        }
        if ($_POST['Max_Time'] == 'inherit') {
            $MaxTime = @ $groupSettings[$group]['MaxTime'];
        }

        // We create the batch first, then add users to it (prevents us having unattached users if the batch dies for some reason)
        $batchID = $Settings->nextBatchID();
        $Settings->saveBatch($batchID, array(), $Auth->getUsername(), \Grase\Clean::text($_POST['Comment']));
        $Settings->setSetting('lastbatch', $batchID);

        $failedUsers = 0;
        for ($i = 0; $i < $user['numberoftickets']; $i++) {
            $username = \Grase\Util::randomUsername(
                5
            ); // DONE: Username uniqness is checked as user creation time in Database
            $password = \Grase\Util::randomPassword(6);

            // Attempt to create user. Will error if it's not a unique username
            if (DatabaseFunctions::getInstance()->createUser(
                $username,
                $password,
                $MaxMb,
                $MaxTime,
                expiry_for_group($group, $groupSettings),
                $groupSettings[$group]['ExpireAfter'],
                \Grase\Clean::text($_POST['Group']),
                \Grase\Clean::text($_POST['Comment'])
            )
            ) {
                AdminLog::getInstance()->log("Created new user $username");
                $Settings->addUserToBatch($batchID, $username);
                $createdUsernames[] = $username;
            } else {
                // Failed to create. Most likely not a unique username.
                // Try again but only for so long (i.e. all usernames are in use)
                $i--;

                // This really chokes up the logs, maybe don't log this? TODO
                AdminLog::getInstance()->log("Failed to created new user $username. Probably duplicate username");
                $failedUsers++;

                if ($failedUsers > 20) {
                    AdminLog::getInstance()->log("Too many failed usernames, stopping batch creation");
                    $error[] = sprintf(
                        T_(
                            "Too many users failed to create. Batch creation stopped. %s users have been successfully created"
                        ),
                        $i
                    );
                    break;
                }
            }
        }

        // Load up user details of created users for displaying
        $createdUsers = DatabaseFunctions::getInstance()->getMultipleUsersDetails($createdUsernames);
        $templateEngine->assign("createdusers", $createdUsers);

        // Check if we managed to create all users or if batch failed
        if ($failedUsers <= 20) {
            $success[] = T_("Tickets Successfully Created");
            $success[] = "<a target='_tickets' href='printnewtickets'>" . T_("Print Tickets") . "</a>";
        }
        $templateEngine->assign("success", $success);
        $templateEngine->assign("error", $error);
    }
}

// TODO: make default settings customisable
$user['Max_Mb'] = 'inherit';
$user['Max_Time'] = 'inherit';
$templateEngine->assign("user", $user);

$templateEngine->assign("last_batch", $Settings->getSetting('lastbatch'));
$templateEngine->assign("listbatches", $Settings->listBatches());

$templateEngine->displayPage('newtickets.tpl');
