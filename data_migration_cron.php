<?php

if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

$temp = exec('ps -ef | grep "php data_migration_cron.php" | grep -v "grep" -c',$tmp1,$tmp2);
if((int)$tmp2 == 0 && (int)$temp==1){
}else{exit;}

require_once dirname(__FILE__).'/../multitenant/Classes/dataMigrationClass.php';
require_once dirname(__FILE__).'/../multitenant/Classes/s3Class.php';

class data_migration_cron extends dataMigrationClass{

    var $allow_rollback = true;
    var $selected_task_id = 0;
    var $start_time = "00:00:00";
    var $stop_time = "23:59:59";
    var $begin_time = 0;
    var $begin_time2 = 0;
    var $end_time = 0;
    var $s3Class = false;
    var $tmp_file = false;
    var $related_table = array();
    var $email_list = array('jacky.low@mims.com','gunalan.uthayasoorian@mims.com');

    public function __construct() {
        //ini_set("display_errors", "off");
        /*
        set_error_handler(function ($iErrorNumber, $sErrorMessage, $sErrorFile, $sErrorLine) {
            throw new ErrorException($sErrorMessage, $iErrorNumber, 0, $sErrorFile, $sErrorLine);
        });
        */
        set_exception_handler(function ($oException) {
            $message = $oException->getMessage();
            $this->write_error($message);
            die("error1: ".$message);
        });
        register_shutdown_function(function () {
            $last_error = error_get_last();
            if($last_error['type']===1){
                $message = $last_error['message'];
                //throw new ErrorException($last_error['message'], $last_error['type'], 0, $last_error['file'], $last_error['line']);
                $this->write_error($message);
                die("error2: ".$message);
            }
        });

        parent::__construct();

        $this->related_table = array('tbase_patient_data','tbase_form_encounter','tbase_forms','tbase_billing_header','tbase_prescriptions','tbase_billing','tbase_ar_session','tbase_ar_activity','tbase_payments');

        $this->s3Class = new s3Class;

        $this->curl_opt = getopt('', array('verbose','outdir::'));
        if(isset($this->curl_opt['verbose'])){
            $this->enable_log = true;
        }
        if(isset($this->curl_opt['outdir']) && strlen($this->curl_opt['outdir'])>0 && file_exists($this->curl_opt['outdir'])){
            $this->log_location_dir = $this->curl_opt['outdir'];
        }
    }

    public function __destruct(){
        $this->delete_tmp_file();
    }

    public function set_usedtime(){
        $this->begin_time = strtotime(date("Y-m-d H:i:s"));
        $this->begin_time2 = $this->begin_time;
    }

    public function show_usedtime($show_begining = false){
        $return = false;
        if($this->begin_time>0){
            $this->end_time = strtotime(date("Y-m-d H:i:s"));
            if($show_begining){
                $ttime = $this->end_time - $this->begin_time2;
            }else{
                $ttime = $this->end_time - $this->begin_time;
            }
            $htime = max(0,floor($ttime / 60 / 60));
            $mtime = max(0,floor(($ttime - ($htime*60*60))/60));
            $stime = $ttime - ($htime*60*60) - ($mtime*60);
            $return = (($htime>0)?$htime." Hour".(($htime>1)?"s ":""):"").(($mtime>0)?$mtime." Minute".(($mtime>1)?"s ":""):"").(($stime>0)?$stime." Second".(($stime>1)?"s ":""):"");
            if(trim($return)==""){
                $return = '< 1 Sec';
            }
        }
        $this->begin_time = strtotime(date("Y-m-d H:i:s"));
        return $return;
    }

    public function write_error($message,$id = 0){
        if(strlen(trim($message))==0){
            return false;
        }
        if($id==0){
            $id = $this->selected_task_id;
        }
        if($id>0){
            $temp = "\n".$message;
            $query = 'UPDATE data_migration_list SET dm_error_message = concat(ifnull(dm_error_message,""),?) WHERE id=? LIMIT 1';
            $bind_array = array($temp,$id);
            $this->_db_execute($query, $bind_array);

            //add into log
            $this->log_message($message);
        }
    }

    public function sendmail($email=array(),$msg_body="",$subject=""){
        if(!is_array($email)){
            $email = array($email);
        }
        $msg_body = nl2br($msg_body);
        // phpmailer init
        require_once dirname(__FILE__).'/../library/classes/class.phpmailer.php';
        include dirname(__FILE__).'/../../env.php';
        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['user'];
        $mail->Password = $smtp['pass'];
        $mail->SMTPSecure = 'tls';
        $mail->Port = $smtp['port'];
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        foreach($email as $em){
            $mail->addAddress($em);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $msg_body;
        $mail->send();
    }

    public function delete_tmp_file(){
        try{
            if(strlen($this->tmp_file)>0 && file_exists($this->tmp_file)){
                unlink($this->tmp_file);
            }
        } catch (Exception $ex) {

        }
        $this->tmp_file = false;
    }

    public function task_process_check(){
        $query = 'SELECT id,dm_tenant_id,dm_error_message FROM data_migration_list WHERE dm_status=1 ORDER BY dm_created_date ASC';
        if(($result = $this->_db_query($query))){
            foreach($result as $row){
				$id = $row['id'];
				$tenant_id = $row['dm_tenant_id'];
				$message = $row['dm_error_message'];
                $this->write_error("PHP process terminated!", $id);
                if($this->allow_rollback){
                    $this->_db_execute('UPDATE data_migration_list SET dm_status=7 WHERE dm_status=1 AND id=? LIMIT 1',array($id));
                }else{
                    $this->_db_execute('UPDATE data_migration_list SET dm_status=9 WHERE dm_status=1 AND id=? LIMIT 1',array($id));
                    $this->sendmail($this->email_list, $message, $tenant_id." - data migration fail");
                }
            }
        }

        $query = 'SELECT id,dm_tenant_id,dm_error_message FROM data_migration_list WHERE dm_status=7 ORDER BY dm_created_date DESC';
        if(($result = $this->_db_query($query))){
            foreach($result as $row){
				$id = $row['id'];
				$tenant_id = $row['dm_tenant_id'];
				$message = $row['dm_error_message'];
                $this->write_error("Rollback Checking.", $id);
                $this->task_rollback($id, $tenant_id);
                $this->sendmail($this->email_list, $message, $tenant_id." - data migration fail and rollback");
            }
        }
    }

    public function task_start(){
        $query = 'SELECT id,dm_data,dm_tenant_id,dm_facility_id FROM data_migration_list WHERE dm_status=0 and dm_created_date <= now() ORDER BY dm_created_date ASC LIMIT 1';
        if(($result = $this->_db_query($query))){
            $data = $result[0];
            $this->selected_task_id = $data['id'];
            $tenant_id = $data['dm_tenant_id'];
            $facility_id = $data['dm_facility_id'];

            $query = 'UPDATE data_migration_list SET dm_status=? WHERE id=? LIMIT 1';
            $bind_array = array('1',$this->selected_task_id);
            $this->_db_execute($query, $bind_array);

            $this->write_error("Start time: ".date(DATE_RFC2822));
            $this->set_usedtime();

            if($this->allow_rollback){
                $this->task_backup($this->selected_task_id, $tenant_id);
            }
            
            $data_type = json_decode($data['dm_data']);
            $error = 0;
            $success = 0;
            foreach($data_type as $k => $fp){
                if(!is_array($fp)){
                    $fp = array($fp);
                }
                foreach($fp as $p){
                    $this->delete_tmp_file();
                    $location = $p;
                    $trial_result = false;
                    if(($file_contents = $this->s3Class->bucket_get($location))){
                        $file = tempnam(sys_get_temp_dir(), 'excel_');
                        file_put_contents($file, $file_contents);
                        $this->tmp_file = $file;

                        if(strtolower(substr(basename($location), -3))=="zip" && ($zip = zip_open($file)) && is_resource($zip)){
                            $count = 0;
                            $count2= 0;
                            while ($zip_entry = zip_read($zip)) {
                                $count++;
                                if (zip_entry_open($zip, $zip_entry, "r")) {
                                    $file_contents = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                                    $file_name = zip_entry_name($zip_entry);
                                    zip_entry_close($zip_entry);
                                    $file = tempnam(sys_get_temp_dir(), 'excel_');
                                    file_put_contents($file, $file_contents);
                                    $trial_result = $this->task_start_process($tenant_id, $facility_id, $file_name, $file, $k);
                                    if(file_exists($file)){
                                        unlink($file);
                                    }
                                    if(is_array($trial_result) && $trial_result['process_end']==1 && is_array($trial_result['data']) && sizeof($trial_result['data'])>0){
                                        $count2++;
                                    }
                                }
                            }
                            zip_close($zip);
                            if($count==$count2){
                                $success++;
                                continue;
                            }
                        }else{
                            $trial_result = $this->task_start_process($tenant_id, $facility_id, $location, $file, $k);
                            if(file_exists($file)){
                                unlink($file);
                            }
                            if(is_array($trial_result) && $trial_result['process_end']==1 && is_array($trial_result['data']) && sizeof($trial_result['data'])>0){
                                $success++;
                                continue;
                            }
                        }
                    }

                    $error++;
                    if(is_array($trial_result) && $trial_result['total_records']>0 && $trial_result['total_valid']==0){
                        $this->write_error(ucwords(strtolower($k))." migration: No data.");
                        //continue;
                    }else{
                        $this->write_error(ucwords(strtolower($k))." migration: Fail.");
                    }
                    break(2);
                }
            }
            $this->delete_tmp_file();

            $status = '9';
            if($success>0 && $error==0){
                $status = '2';
                $this->write_error("Migration status: Success");
            }else{
                $this->write_error("Migration status: Fail");
            }
            $query = 'UPDATE data_migration_list SET dm_status=? WHERE id=? LIMIT 1';
            $bind_array = array($status,$data['id']);
            $this->_db_execute($query, $bind_array);

            if($success>0 && $error>0){
                $this->task_rollback($this->selected_task_id, $tenant_id);
            }

            $this->write_error("Completed time: ".date(DATE_RFC2822));
            $this->write_error("Total used time: ".$this->show_usedtime(true));

            foreach($data_type as $k => $fp){
                if(!is_array($fp)){
                    $fp = array($fp);
                }
                foreach($fp as $p){
                    $location = $p;
                    $this->s3Class->bucket_delete($location);
                }
            }

            //sendmail
            if(($result = $this->_db_query('SELECT dm_error_message FROM data_migration_list WHERE id=? LIMIT 1',array($data['id'])))){
                $message = $result[0]['dm_error_message'];
                if($success>0 && $error==0){
                    $this->sendmail($this->email_list, $message, $tenant_id." - data migration success");
                }else{
                    $this->sendmail($this->email_list, $message, $tenant_id." - data migration fail");
                }
            }

            return true;
        }

        return false;
    }

    public function task_start_process($tenant_id,$facility_id,$file_name,$file_location,$migrate_type){
        $trial_result = false;
        $dmClass = new dataMigrationClass;
        $dmClass->set_tenant($tenant_id);
        $dmClass->facility_id = $facility_id;
        $dmClass->migrate_type = $migrate_type;
        $dmClass->allow_skip = 1;
        $dmClass->error_abort = 0;
        $dmClass->argv['--file']  = $file_location;
        $dmClass->real_filename = basename($file_name);
        $dmClass->dm_id = $this->selected_task_id;

        $func   = "migrate_".$dmClass->migrate_system."_".$dmClass->migrate_type;
        if (method_exists($dmClass, $func)) {
            $trial_result = call_user_func(array($dmClass, $func), true);

            if($trial_result){
                $this->write_error("File Processed: ".$dmClass->real_filename);
                $this->write_error("Is Error: ".(($trial_result['process_end']==1)?"No":"Yes"));
                $this->write_error("Total Records: ".$trial_result['total_records']);
                $this->write_error("Total Valid: ".$trial_result['total_valid']);
                $this->write_error("Total Exists: ".$trial_result['total_mapped']);
                $this->write_error("Total Invalid: ".$trial_result['total_fail']);
                $this->write_error("Reading time: ".$this->show_usedtime());

                if($trial_result['process_end']==1 && is_array($trial_result['data']) && sizeof($trial_result['data'])>0){
                    $func   = "migrate_proceed_".$dmClass->migrate_system."_".$dmClass->migrate_type;
                    if (method_exists($dmClass, $func)) {
                        if(call_user_func(array($dmClass, $func), $trial_result['data'])){
                            $this->write_error("Process time: ".$this->show_usedtime());
                            $this->write_error(ucwords(strtolower($dmClass->migrate_type))." migration: Upload successfully.");
                            return $trial_result;
                        }
                    }
                }

                if(isset($trial_result['error_list']) && sizeof($trial_result['error_list'])>0){
                    $this->write_error("Messages: ");
                    ob_start();
                    print_r($trial_result['error_list']);
                    $message = ob_get_clean();
                    $this->write_error($message);
                }
            }
        }
        return false;
    }

    public function task_backup($id=0,$tenant_id=""){
        return false;
        //create backup
        /*
        $this->write_error("Task backup.", $id);
        $tclass = new dataMigrationClass;
        if($tclass->set_tenant($tenant_id)){
            foreach($this->related_table as $table){
                $new_table = "dm_".$table;
                $this->_db_execute('START TRANSACTION;');
                $this->_db_execute('DROP TABLE IF EXISTS '.$new_table);
                $this->_db_execute('CREATE TABLE IF NOT EXISTS '.$new_table.' SELECT * FROM '.$table.' WHERE tenant_id=?',array($tenant_id));
                $this->_db_execute('ALTER TABLE '.$new_table.' ENGINE=InnoDB;');
                $this->_db_execute('COMMIT;');
            }
        }
        */
    }

    public function task_rollback($id=0,$tenant_id=""){
        $this->write_error("Task rollback.", $id);

        //clear insert records
        $tclass = new dataMigrationClass;
        if($tclass->set_tenant($tenant_id)){
            $sql = 'SET unique_checks=0';
            sqlStatement($sql);
            $sql = 'SET foreign_key_checks=0;';
            sqlStatement($sql);
            /*
            foreach($this->related_table as $table){
                $new_table = "dm_".$table;
                $sql = 'START TRANSACTION;';
                sqlStatement($sql);
                sqlStatement('DELETE FROM '.$table.' WHERE tenant_id=?', array($tenant_id));
                sqlStatement('INSERT IGNORE INTO '.$table.' SELECT * FROM '.$new_table.' WHERE tenant_id=?', array($tenant_id));
                if(($result = $this->_db_query('SHOW COLUMNS FROM '.$table))){
                    $field = false;
                    foreach($result as $row){
                        if ($row['Extra'] == 'auto_increment') {
                            $field = $row['Field'];
                            break;
                        }
                    }
                    if($field && ($result = $this->_db_query('SELECT MAX('.$field.') field FROM '.$table)) && (int)$result[0]['field']>0){
                        sqlStatement('ALTER TABLE '.$table.' AUTO_INCREMENT='.((int)$result[0]['field'] + 1));
                    }
                }
                $sql = 'COMMIT;';
                sqlStatement($sql);
            }
            */

            $patient_list = array();
            if (($temp_result    = sqlStatement("select pid from patient_data WHERE dm_id=?",array($id)))) {
                while ($patient_data = sqlFetchArray($temp_result)) {
                    $temp2                  = md5($patient_data['pid']);
                    $patient_list[$temp2] = $patient_data['pid'];
                }
            }
            foreach($patient_list as $d){
                $sql = 'START TRANSACTION;';
                sqlStatement($sql);
                sqlStatement('DELETE FROM insurance_data WHERE pid=?',array($d));
                sqlStatement('DELETE FROM patient_data WHERE pid=? and dm_id=?',array($d,$id));
                $sql = 'COMMIT;';
                sqlStatement($sql);
            }
            
            $encounter_list = array();
            if (($temp_result    = sqlStatement("select a.encounter,b.session_id from tbase_form_encounter a
                    left join tbase_ar_activity b on a.tenant_id=b.tenant_id and a.encounter=b.encounter WHERE a.dm_id=?",
                array($id)))) {
                while ($patient_data = sqlFetchArray($temp_result)) {
                    $temp2                  = md5($patient_data['encounter']);
                    $encounter_list[$temp2] = array('encounter'=>$patient_data['encounter'],'session_id'=>$patient_data['session_id']);
                }
            }
            foreach($encounter_list as $d){
                $sql = 'START TRANSACTION;';
                sqlStatement($sql);
                sqlStatement('DELETE FROM payments WHERE encounter=?',array($d['encounter']));
                sqlStatement('DELETE FROM ar_activity WHERE encounter=?',array($d['encounter']));
                sqlStatement('DELETE FROM ar_session WHERE session_id=?',array($d['session_id']));
                sqlStatement('DELETE FROM billing WHERE encounter=?',array($d['encounter']));
                sqlStatement('DELETE FROM prescriptions WHERE encounter=?',array($d['encounter']));
                sqlStatement('DELETE FROM billing_header WHERE encounter=?',array($d['encounter']));
                sqlStatement('DELETE FROM forms WHERE encounter=?',array($d['encounter']));
                sqlStatement('DELETE FROM form_encounter WHERE encounter=?',array($d['encounter']));
                $sql = 'COMMIT;';
                sqlStatement($sql);
            }

            $sql = 'SET unique_checks=1';
            sqlStatement($sql);
            $sql = 'SET foreign_key_checks=1;';
            sqlStatement($sql);
        }

        $query = 'UPDATE data_migration_list SET dm_status=? WHERE id=? LIMIT 1';
        $bind_array = array('8',$id);
        $this->_db_execute($query, $bind_array);
    }

    public function exec(){
        $maintenance_file = dirname(__FILE__).'/../../maintenance.enable';
        $maintenance_file2 = dirname(__FILE__).'/../../data_migration_cron.enable';
        $start_time = strtotime(date("Y-m-d ".$this->start_time));
        $end_time = strtotime(date("Y-m-d ".$this->stop_time));
        if($start_time >= $end_time){
            $start_time = strtotime('-1 day',strtotime($start_time));
        }
        /*
        if(!file_exists($maintenance_file2)){
            file_put_contents($maintenance_file2, "");
        }
        */
        $this->task_process_check();

        $cur_time = strtotime(date("Y-m-d H:i:s"));
        if($cur_time<$start_time || $cur_time>$end_time){

        }else{
            $this->task_start();
        }
        /*
        if(file_exists($maintenance_file2)){
            unlink($maintenance_file2);
        }
        */
        $this->task_process_check();
    }
}

$class = new data_migration_cron;
$class->exec();
