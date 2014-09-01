<?php

namespace Grase\Database;

// All Radmin Database functions (Was Settings)
class Radmin
{

    protected $radmin;

    private $settingcache = array();
    private $settingcacheloaded = false;

    private $dbSchemeVersion = "2.2";
    private $dbSchemeSettings =
        "CREATE TABLE IF NOT EXISTS `settings` (
          `setting` varchar(20) NOT NULL,
          `value` varchar(1000) NOT NULL,
          PRIMARY KEY (`setting`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Settings for RAFI interface'";
    private $dbSchemeAuth =
        "CREATE TABLE IF NOT EXISTS `auth` (
          `username` varchar(50) NOT NULL DEFAULT '',
          `password` varchar(60) NOT NULL,
          PRIMARY KEY (`username`),
          KEY `password` (`password`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1";
    private $dbSchemaTemplates =
        "CREATE TABLE IF NOT EXISTS `templates` (
          `id` tinyint(4) NOT NULL,
          `tpl` text NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='HTML/CSS Storage'";

    private $dbSchemaBatch =
        "CREATE TABLE IF NOT EXISTS `batch` (
	        `batchID` INT UNSIGNED NOT NULL,
	        `UserName` VARCHAR(64) NOT NULL,
             KEY `username` (`UserName`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Stores users batch when auto created'";

    private $dbSchemaBatches =
        "CREATE TABLE IF NOT EXISTS `batches` (
	        `batchID` INT NOT NULL,
	        `createTime` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
	        `createdBy` VARCHAR(64) NOT NULL,
	        `Comment` VARCHAR(300),
	        PRIMARY KEY `batchid` (`batchID`)
        ) ENGINE=MyISAM COMMENT ='Batches'";

    private $dbSchemaGroups =
        "CREATE TABLE `groups` (
            `GroupName` VARCHAR(64) NOT NULL,
            `GroupLabel` VARCHAR(64) NOT NULL,
            `Expiry` VARCHAR(100) NULL,
            `MaxOctets` BIGINT(32) UNSIGNED NULL,
            `MaxSeconds` BIGINT(32) UNSIGNED NULL,
            `Comment` VARCHAR(300) NULL,
            `lastupdated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY `GroupName` (`GroupName`)
        ) ENGINE=MyISAM COMMENT ='Groups'";

    private $dbSchemaVouchers =
        "CREATE TABLE `vouchers` (
        `VoucherName` VARCHAR(64) NOT NULL,
        `VoucherLabel` VARCHAR(64) NOT NULL,
        `VoucherPrice` VARCHAR(64) NOT NULL,
        `VoucherGroup` VARCHAR(64) NOT NULL,
        `MaxOctets` BIGINT(32) UNSIGNED NULL,
        `MaxSeconds` BIGINT(32) UNSIGNED NULL,
        `Description` VARCHAR(300) NULL,
        `VoucherType` INT(32) UNSIGNED NOT NULL,
        `lastupdated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY `VoucherName` (`VoucherName`)
        ) ENGINE=MyISAM COMMENT ='Vouchers'";

    public function __construct(Database $radmin)
    {
        $this->radmin = $radmin->conn;
        if (!$this->checkTablesExist()) {
            $this->createTables();
        }

        // Load all settings as we ALWAYS need settings (do we?) TODO
        $this->loadAllSettings();
    }

    private function checkTablesExist()
    {
        try {
            $results = $this->radmin->query("SHOW TABLES")->fetchAll(
                \PDO::FETCH_COLUMN
            );
        } catch (\PDOException $Exception) {
            \Grase\ErrorHandling::fatal_db_error(
                T_(
                    'Get All Settings for caching Query failed: '
                ) . $Exception->getMessage(),
                null
            );
        }

        foreach ($results as $row) {
            /* To ensure if database names change, we don't know the column
             * name (it could be Tables_in_radmin) so just take the first
             * column of the array */
            $tables[$row] = true;
        }

        if (isset($tables['settings']) &&
            isset($tables['auth']) &&
            isset($tables['templates']) &&
            isset($tables['batch']) &&
            isset($tables['batches']) &&
            isset($tables['groups']) &&
            isset($tables['vouchers'])
        ) {
            return true;
        }
        return false;

    }

    private function createTables()
    {
        // Settings Table
        $this->radmin->query($this->dbSchemeSettings);
        // Auth Table
        $this->radmin->query($this->dbSchemeAuth);
        // Templates table
        $this->radmin->query($this->dbSchemaTemplates);
        // Batch table
        $this->radmin->query($this->dbSchemaBatch);
        // Batches table
        $this->radmin->query($this->dbSchemaBatches);
        // Groups Table
        $this->radmin->query($this->dbSchemaGroups);
        // Vouchers Table
        $this->radmin->query($this->dbSchemaVouchers);
        $this->setSetting('DBSchemaVersion', $this->dbSchemeVersion);
    }

    private function loadAllSettings($force = true)
    {
        if ($this->settingcacheloaded && force == false) {
            return true;
        }

        // Load everything into a cache as needed (make sure we update the
        // cache on updates
        $sql = "SELECT setting, value FROM settings";

        $results = $this->radmin->query($sql)->fetchAll();

        foreach ($results as $row) {
            $this->settingcache[$row['setting']] = $row['value'];
        }

        $this->settingcacheloaded = true;


    }

    public function getSetting($setting)
    {
        if ($this->settingcacheloaded) {
            if (isset($this->settingcache[$setting])) {
                return $this->settingcache[$setting];
            }
            return null;
        }

        $select = $this->radmin->prepare(
            "SELECT value FROM settings WHERE setting = ?"
        );
        $select->execute(array($setting));


        $result = $select->fetch();
        // Always check that result is not an error
        if ($result === false) {
            \Grase\ErrorHandling::fatal_db_error(
                'Getting setting failed: ',
                $result
            );
        }

        return $result['value'];

    }

    public function checkExistsSetting($setting)
    {
        if ($this->settingcacheloaded && isset($this->settingcache[$setting])) {
            return true;
        }

        $sql = $this->radmin->prepare(
            "SELECT COUNT(setting) as settingcount
            FROM settings WHERE setting = ? LIMIT 1"
        );
        $sql->execute(array($setting));
        return (bool)$sql->fetch()['settingcount'];

    }

    public function setSetting($setting, $value)
    {
        if ($this->checkExistsSetting($setting) == 0) {
            // Insert new record
            $query = $this->radmin->prepare(
                "INSERT INTO settings SET setting= ?, value=?"
            );
            $params = array($setting, $value);
        } else {
            // Update old record
            $query = $this->radmin->prepare(
                "UPDATE settings SET value= ? WHERE setting= ?"
            );
            $params = array($value, $setting);
        }

        if ($query->execute($params)) {
            // Update settings cache to prevent wrong data
            if ($this->settingcacheloaded) {
                $this->settingcache[$setting] = $value;
            }

            // TODO: Do we still need to filter this out now?
            // lastbatch clogs admin log, filter it out
            if ($setting != 'lastbatch') {
                \AdminLog::getInstance()->log(
                    "Setting $setting updated to $value"
                );
            }
            return true;

        } else {
            \AdminLog::getInstance()->log(
                "Setting $setting failed to update (to $value)"
            );
            \Grase\ErrorHandling::fatal_db_error(
                'Updating setting failed: ',
                null
            );
        }
    }

    /* "Settings" for templates */

    // Template map is to make it easier to lookup templates via int not txt
    private $templatemap = array(
        'maincss' => 0,
        'loginhelptext' => 1,
        'helptext' => 2,
        'belowloginhtml' => 3,
        'loggedinnojshtml' => 4,
        'termsandconditions' => 5,
    );

    public function getTemplate($template)
    {
        $query = $this->radmin->prepare(
            "SELECT tpl FROM templates WHERE id = ? LIMIT 1"
        );
        $result = $query->execute(array($this->templatemap[$template]));

        // Always check that result is not an error
        if ($result == false) {
            \Grase\ErrorHandling::fatal_db_error(
                'Getting template failed: ',
                null
            );
        }

        $result = $query->fetch();

        return $result['tpl'];

    }

    public function checkExistsTemplate($template)
    {

        $sql = $this->radmin->prepare(
            "SELECT COUNT(id) as templatecount
            FROM templates WHERE id = ? LIMIT 1"
        );
        $sql->execute(array($this->templatemap[$template]));
        return (bool)$sql->fetch()['templatecount'];

    }

    public function setTemplate($template, $value)
    {
        // if $value == NULL we cause problems (assume user wants empty template
        if ($value == '') {
            $value = ' ';
        }

        if (!isset($this->templatemap[$template])) {
            \Grase\ErrorHandling::fatal_error(
                'Attempt to update non-existent
                             template'
            );
        }
        // Check count not contents ^^
        if ($this->checkExistsTemplate($template) == 0) {
            // Insert new record
            $query = $this->radmin->prepare(
                "INSERT INTO templates SET id=?, tpl=?"
            );
            $params = array($this->templatemap[$template], $value);
        } else {
            // Update old record
            $query = $this->radmin->prepare(
                "UPDATE templates SET tpl=? WHERE id=?"
            );
            $params = array($value, $this->templatemap[$template]);
        }

        if ($query->execute($params)) {
            \AdminLog::getInstance()->log("Template $template updated");
            return true;
        } else {
            \AdminLog::getInstance()->log(
                "Template $template failed to update"
            );
            \Grase\ErrorHandling::fatal_db_error(
                'Updating template failed:
                            ',
                null
            );
        }
    }
    /* End templates functions */

    /* Functions for managing batchs */

    public function saveBatch(
        $batchID,
        $users = array(),
        $createuser = 'Anon(System)',
        $comment = ""
    ) {
        $result = 0;

        $insert = $this->radmin->prepare(
            "INSERT INTO batches SET
                        batchID=?,
                        createdBy=?,
                        Comment=?"
        );
        if ($insert->execute(array($batchID, $createuser, $comment))) {
            $result++;

            // $users is an array of usernames, nothing more
            foreach ($users as $user) {
                if ($this->addUserToBatch($batchID, $user)) {
                    $result++;
                }
            }

            return $result;
        } else {
            AdminLog::getInstance()->log("Batches $batchID failed to add");
            \Grase\ErrorHandling::fatal_db_error(
                'Adding batch failed: ',
                null
            );
        }

    }

    public function addUserToBatch($batchID, $user)
    {
        // Insert new batch/user record
        $query = $this->radmin->prepare(
            "INSERT INTO batch SET
                        batchID=?,
                        UserName=?"
        );
        if ($query->execute(array($batchID, $user))) {
            return true;
        } else {
            AdminLog::getInstance()->log("Batch $batchID failed to add $user");
            \Grase\ErrorHandling::fatal_db_error(
                'Adding user to batch failed: ',
                null
            );
        }
    }

    public function listBatches()
    {
        /* select batches.batchID, createTime, createdBy, comment, count(UserName) from batch, batches WHERE batches.batchID = batch.batchID;*/
        $sql = "SELECT
                  batches.batchID,
                  createTime,
                  createdBy,
                  comment,
                  count(UserName) as numTickets
                FROM
                  batch,
                  batches
                WHERE
                  batches.batchID = batch.batchID
                GROUP BY batches.batchID";

        $result = $this->radmin->query($sql)->fetchAll();

        // Always check that result is not an error
        if ($result == false) {
            \Grase\ErrorHandling::fatal_db_error(
                'Getting list of batches failed: ',
                $result
            );
        }

        return $result;
    }

    public function getBatch($batchID = 0)
    {
        if ($batchID == 0) // Get lastbatch
        {
            $batchID = $this->getSetting('lastbatch');
        }

        $batch = $this->radmin->prepare(
            "SELECT UserName FROM batch WHERE batchID=?"
        );

        $result = $batch->execute(array($batchID));

        // Always check that result is not an error
        if ($result == false) {
            \Grase\ErrorHandling::fatal_db_error(
                'Getting batch users failed: ',
                $result
            );
        }
        return $batch->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function nextBatchID()
    {
        // Get next available BatchID
        // ISNULL/IFNULL aren't standards, COALESCE is
        // GREATEST isn't a standard
        $sql = "SELECT GREATEST(COALESCE(MAX(batch.batchID),0),COALESCE(MAX(batches.batchID),0))+1 AS nextBatchID FROM batch, batches";

        $nextBatchID = $this->radmin->query($sql)->fetchColumn();

        // Always check that result is not an error
        if ($nextBatchID == false) {
            \AdminLog::getInstance()->log("Unable to fetch nextBatchID");
            \Grase\ErrorHandling::fatal_db_error(
                'Fetching nextBatchID
                            failed: ',
                null
            );
        }

        return $nextBatchID;

    }

    /* End batches functions */

    /* Start Group Functions */

    /* getGroup($groupname = '') // Get single group or all groups
     * setGroup($groupname, $settings) // Set group settings
     * deleteGroup($groupname) // Delete group
     */

    public function getGroup($groupname = '')
    {
        if ($groupname != '') {
            $query = $this->radmin->prepare(
                "SELECT
                    GroupName,
                    GroupLabel,
                    Expiry,
                    MaxOctets,
                    MaxSeconds,
                    Comment,
                    lastupdated
                    FROM groups
                    WHERE GroupName=?"
            );
            $query->execute(array($groupname));

        } else {
            $query = $this->radmin->query(
                "SELECT
                    GroupName,
                    GroupLabel,
                    Expiry,
                    MaxOctets,
                    MaxSeconds,
                    Comment,
                    lastupdated
                    FROM groups
                    ORDER BY GroupName"
            );

        }

        $result = $query->fetchAll();

        // Always check that result is not an error
        if ($result == false) {
            \Grase\ErrorHandling::fatal_db_error(
                'Getting groups failed: ',
                $result
            );
        }

        $groups = array();
        foreach ($result as $results) {
            if (isset($results['MaxSeconds'])) {
                $results['MaxTime'] = $results['MaxSeconds'] / 60;
            }
            if (isset($results['MaxOctets'])) {
                $results['MaxMb'] = $results['MaxOctets'] / 1024 / 1024;
            }
            $groups[$results['GroupName']] = $results;
        }

        return $groups;

    }

    public function setGroup($attributes)
    {

        if (isset($attributes['MaxMb'])) {
            $attributes['MaxOctets'] = \Grase\Util::bigIntVal(
                $attributes['MaxMb'] * 1024 * 1024
            );
            unset($attributes['MaxMb']);
        }

        if (isset($attributes['MaxTime'])) {
            $attributes['MaxSeconds'] = $attributes['MaxTime'] * 60;
            unset($attributes['MaxTime']);
        }

        // We can't just use the $attributes array as it has to match exactly
        // the number and names of the prepared statement
        $fields = array(
            'GroupName' => $attributes['GroupName'],
            'GroupLabel' => $attributes['GroupLabel'],
            'Expiry' => @ $attributes['Expiry'],
            'MaxOctets' => @ $attributes['MaxOctets'],
            'MaxSeconds' => @ $attributes['MaxSeconds'],
            'Comment' => @ $attributes['Comment']
        );

        $query = $this->radmin->prepare(
            "INSERT INTO groups
            (GroupName, GroupLabel, Expiry, MaxOctets, MaxSeconds, Comment)
            VALUES
            (:GroupName, :GroupLabel, :Expiry, :MaxOctets, :MaxSeconds, :Comment)
            ON DUPLICATE KEY UPDATE
            GroupLabel = :GroupLabel,
            Expiry = :Expiry,
            MaxOctets = :MaxOctets,
            MaxSeconds = :MaxSeconds,
            Comment = :Comment"
        );

        $result = $query->execute($fields);
        if ($result === false) {
            \Grase\ErrorHandling::fatal_db_error(
                T_('Adding Group query failed:  '),
                $result
            );
        }

        \AdminLog::getInstance()->log(
            "Group " . $attributes['GroupName'] . "
        updated settings"
        );

        return $result;

    }

    public function deleteGroup($groupname)
    {
        $delete = $this->radmin->prepare(
            "DELETE FROM groups WHERE GroupName=?"
        );

        if ($delete->execute(array($groupname)) === false) {
            \Grase\ErrorHandling::fatal_db_error(
                T_('Delete Group query failed:  '),
                $result
            );
        }

        \AdminLog::getInstance()->log("Group $groupname deleted");

        return true;
    }

    /* End Group Functions */

    /* Start Vouchers Functions */

    public function setVoucher($attributes)
    {

        if (isset($attributes['MaxMb'])) {
            $attributes['MaxOctets'] = \Grase\Util::bigIntVal(
                $attributes['MaxMb'] * 1024 * 1024
            );
            unset($attributes['MaxMb']);
        }

        if (isset($attributes['MaxTime'])) {
            $attributes['MaxSeconds'] = $attributes['MaxTime'] * 60;
            unset($attributes['MaxTime']);
        }

        $attributes['VoucherType'] = 0;
        if ($attributes['InitVoucher']) {
            $attributes['VoucherType'] = 1 | $attributes['VoucherType'];
        }

        if ($attributes['TopupVoucher']) {
            $attributes['VoucherType'] = 2 | $attributes['VoucherType'];

        }

        $fields = array(
            'VoucherName' => $attributes['VoucherName'],
            'VoucherLabel' => $attributes['VoucherLabel'],
            'VoucherPrice' => $attributes['VoucherPrice'] + 0,
            'VoucherGroup' => $attributes['VoucherGroup'],
            'MaxOctets' => @ $attributes['MaxOctets'],
            'MaxSeconds' => @ $attributes['MaxSeconds'],
            'Description' => @ $attributes['Description'],
            'VoucherType' => $attributes['VoucherType']
        );

        $query = $this->radmin->prepare(
            "INSERT INTO vouchers
            (VoucherName, VoucherLabel, VoucherPrice, VoucherGroup,
            MaxOctets, MaxSeconds, Description, VoucherType)
            VALUES
            (:VoucherName, :VoucherLabel, :VoucherPrice, :VoucherGroup,
            :MaxOctets, :MaxSeconds, :Description, :VoucherType)
            ON DUPLICATE KEY UPDATE
            VoucherLabel = :VoucherLabel,
            VoucherPrice = :VoucherPrice,
            VoucherGroup = :VoucherGroup,
            MaxOctets = :MaxOctets,
            MaxSeconds = :MaxSeconds,
            Description = :Description,
            VoucherType =:VoucherType"
        );

        $result = $query->execute($fields);
        if ($result === false) {
            \Grase\ErrorHandling::fatal_db_error(
                T_('Adding Voucher query failed:  '),
                $result
            );
        }

        \AdminLog::getInstance()->log(
            "Voucher " . $attributes['VoucherName'] . "
         updated settings"
        );

        return $result;

    }

    public function getVoucher(
        $vouchername = '',
        $vouchergroup = '',
        $vouchertype = ''
    ) {
        $sql = "SELECT
                VoucherName,
                VoucherLabel,
                VoucherPrice,
                VoucherGroup,
                MaxOctets,
                MaxSeconds,
                Description,
                VoucherType,
                lastupdated
                FROM vouchers";

        $prev_stat = false;
        $wheresql = '';
        $params = array();

        if ($vouchername != '') {
            $wheresql .= "VoucherName=?";
            $params[] = $vouchername;
            $prev_stat = true;
        }
        if ($vouchergroup != '') {
            if ($prev_stat) {
                $wheresql .= " AND ";
            }
            $wheresql .= "VoucherGroup=?";
            $params[] = $vouchergroup;
            $prev_stat = true;
        }

        if ($vouchertype != '') {
            if ($prev_stat) {
                $wheresql .= " AND ";
            }
            $wheresql .= "(VoucherType & ?) > 0";
            $params[] = $vouchertype;
            $prev_stat = true;
        }

        if ($prev_stat && $wheresql != '') {
            $sql .= " WHERE " . $wheresql;
        }

        $query = $this->radmin->prepare($sql);

        $result = $query->execute($params);

        // Always check that result is not an error
        if ($result === false) {
            \Grase\ErrorHandling::fatal_db_error(
                'Getting vouchers failed: ',
                $result
            );
        }

        foreach ($query->fetchAll() as $results) {
            if (isset($results['MaxSeconds'])) {
                $results['MaxTime'] = $results['MaxSeconds'] / 60;
            }
            if (isset($results['MaxOctets'])) {
                $results['MaxMb'] = $results['MaxOctets'] / 1024 / 1024;
            }
            if (isset($results['VoucherType'])) {
                if (($results['VoucherType'] & 1) > 0) {
                    $results['InitVoucher'] = true;
                }
                if (($results['VoucherType'] & 2) > 0) {
                    $results['TopupVoucher'] = true;
                }
            }
            $vouchers[$results['VoucherName']] = $results;
        }

        return $vouchers;

    }

    public function deleteVoucher($vouchername)
    {
        $delete = $this->radmin->prepare(
            "DELETE FROM vouchers WHERE VoucherName=?"
        );
        $result = $delete->execute(array($vouchername));

        if ($result === false) {
            \Grase\ErrorHandling::fatal_db_error(
                T_('Delete Voucher query failed:  '),
                $result
            );
        }

        \AdminLog::getInstance()->log("Voucher $vouchername deleted");

        return $result;
    }


    /* End Vouchers Functions */
} 