<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once 'db_conn.php';

// get all table
// $result = mysqli_query($link, "SHOW FULL TABLES WHERE Table_Type != 'VIEW'");
$result = mysqli_query($link, "SHOW TABLES");
$tables = array();
while ($r = $result->fetch_array()) {
//     //var_dump($r);
    $tables[] = $r[0];
}
// var_dump($tables); die;
mysqli_free_result($result);
// $result = mysqli_query($link, $query) or die(mysqli_error($link));

// create table tenants
echo "Creating table: tenants\n";
$query = "CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(64) NOT NULL UNIQUE,
  `tenant_id` varchar(32) NOT NULL UNIQUE,
  `created_by` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
mysqli_query($link, $query) or die(mysqli_error($link));

// bypass common table
require_once 'common_table.php';
echo "Updating tables ...\n";
// update all tables
foreach ($tables as $table) {
    if (! in_array($table, $common_table)) {
        $tbase_name = 'tbase_' . $table;
        // rename table with tbase_ prefix
        $query = "ALTER TABLE $table RENAME $tbase_name;";
        mysqli_query($link, $query) or die(mysqli_error($link));

        // add tenant_id
        $query = "ALTER TABLE $tbase_name ADD tenant_id varchar(32) DEFAULT NULL;"; // change the DEFAULT based on db user
        mysqli_query($link, $query) or die(mysqli_error($link));

        // create index
        $query = "CREATE INDEX tenant_id_index ON ". $tbase_name ." (tenant_id);";
        mysqli_query($link, $query) or die(mysqli_error($link));
        // create index on tbase_gacl_aro
        if ($table == 'gacl_aro') {
            $query = "DROP INDEX gacl_section_value_value_aro ON ". $tbase_name .";";
            mysqli_query($link, $query) or die(mysqli_error($link));
            $query = "CREATE UNIQUE INDEX gacl_section_value_value_aro ON ". $tbase_name ." (`section_value`, `value`, `tenant_id`);";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }
        // create index on tbase_patient_data
        if ($table == 'patient_data') {
            $query = "DROP INDEX pid ON ". $tbase_name .";";
            mysqli_query($link, $query) or die(mysqli_error($link));
            $query = "CREATE UNIQUE INDEX pid ON ". $tbase_name ." (`pid`, `tenant_id`);";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }
        // create index on tbase_insurance_data
        if ($table == 'insurance_data') {
            $query = "DROP INDEX pid_type_date ON ". $tbase_name .";";
            mysqli_query($link, $query) or die(mysqli_error($link));
            $query = "CREATE UNIQUE INDEX pid_type_date ON ". $tbase_name ." (`pid`, `type`, `date`, `tenant_id`);";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }
        // create index on tbase_user_settings
        if ($table == 'user_settings') {
            $query = "CREATE UNIQUE INDEX `setting_user_label` ON ". $tbase_name ." (`setting_user`, `setting_label`, `tenant_id`);";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }
        // create index on tbase_lists_touch
        if ($table == 'lists_touch') {
            $query = "CREATE UNIQUE INDEX `pid_type` ON ". $tbase_name ." (`pid`, `type`, `tenant_id`);";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }

        // create view
        $query = "CREATE VIEW $table AS SELECT * FROM $tbase_name WHERE tenant_id = SUBSTRING_INDEX(USER(), '@', 1);";
        mysqli_query($link, $query) or die(mysqli_error($link));

        // bypass table
        $bypass_trigger = array('form_encounter');
        // create trigger
        if (! in_array($table, $bypass_trigger)) {
            $query = "CREATE TRIGGER ". $tbase_name ."_tenant_id_insert_trigger
                BEFORE INSERT ON $tbase_name
                FOR EACH ROW SET NEW.tenant_id = SUBSTRING_INDEX(USER(), '@', 1);";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }

        // specific trigger for table form_encounter
        if ($table == 'form_encounter') {
            $query = "DROP TRIGGER IF EXISTS `form_encounter_before_insert`;";
            mysqli_query($link, $query) or die(mysqli_error($link));

            $query = "
                CREATE TRIGGER `form_encounter_before_insert` BEFORE INSERT ON `tbase_form_encounter` FOR EACH ROW BEGIN
                    DECLARE queue_no BIGINT;
                    SET @queue_no=(SELECT COUNT(DATE) AS queue_no FROM form_encounter WHERE DATE(DATE) = DATE(NOW()) AND facility_id = NEW.facility_id);
                    IF (@queue_no < 1000) THEN
                     SET @queue_no = @queue_no + 1000;
                    END IF;
                    SET NEW.queue_no = @queue_no + 1;
                    SET NEW.tenant_id = SUBSTRING_INDEX(USER(), '@', 1);
                END
            ";
            mysqli_query($link, $query) or die(mysqli_error($link));
        }

    }
}
echo "Database has been updated.\n";
