<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once dirname(__FILE__).'/../multitenant/Classes/dataMigrationClass.php';

class data_migration extends dataMigrationClass{
    var $curl_opt = array();
    
    public function __construct() {
        $this->set_usedtime();
        parent::__construct();
        $this->init();
    }

    public function __destruct()
    {
        $this->show_usedtime();
    }

    public function set_usedtime(){
        $this->start_time = strtotime("now");
    }

    public function show_usedtime(){
        if($this->start_time==0){
            return false;
        }
        $this->end_time = strtotime("now");
        $ttime = $this->end_time - $this->start_time;
        $htime = max(0,floor($ttime / 60 / 60));
        $mtime = max(0,floor(($ttime - ($htime*60*60))/60));
        $stime = $ttime - ($htime*60*60) - ($mtime*60);
        print_r("\nUsed Time: ".(($htime>0)?$htime." Hour".(($htime>1)?"s ":""):"").(($mtime>0)?$mtime." Minute".(($mtime>1)?"s ":""):"").(($stime>0)?$stime." Second".(($stime>1)?"s ":""):"")."\n\n");
        $this->start_time = 0;
    }
    
    public function init(){
        $this->curl_opt = getopt('', array('tenant_list','facility_list::','tenant::','facility::','patient::','encounter::','test','proceed','skip-error','verbose','help'));
    }
    
    public function show_help(){
        if(true){
            echo'Data Migration command

Description:
* data migration template must be provided.

list of commands:

> php data_migration.php --tenant_list
        - show tenant list

> php data_migration.php --facility_list="tenant_id"
        - show facility list e.g: id::name

> php data_migration.php --tenant="tenant_id" --patient="patient.xlsx" --test
	- used to test patient data migration and return test result.

> php data_migration.php --tenant="tenant_id" --patient="patient.xlsx" --proceed
	- used to proceed patient data migration.

> php data_migration.php --tenant="tenant_id" --encounter="patient.xlsx" --test
	- used to test encounter data migration and return test result.

> php data_migration.php --tenant="tenant_id" --encounter="patient.xlsx" --proceed
	- used to proceed encounter data migration.

> php data_migration.php --tenant="tenant_id" --patient="patient.xlsx" --test --skip-error
	- used of skip-error will skip the error.

> php data_migration.php --tenant="tenant_id" --facility="branch_id" --patient="patient.xlsx" --test
	- used of facility will set the facility. Default is the first branch.

> php data_migration.php --help
	- used to show help menu.

';
            exit;
        }
    }

    public function show_tenant_list(){
        if(($result = $this->_db_query('SELECT tenant_id FROM tenants order by tenant_id'))){
            print_r($result);
        }else{
            print_r("Not record found! run --help to read more!");
        }
        exit;
    }

    public function show_facility_list($tenant_id=''){
        if($tenant_id=='' && isset($this->curl_opt['tenant'])){
            $tenant_id = $this->curl_opt['tenant'];
        }
        if(($result = $this->_db_query('SELECT id facility_id,name FROM tbase_facility WHERE tenant_id="'.$tenant_id.'" order by name'))){
            print_r($result);
        }else{
            print_r("Facility not found! run --help to read more!");
        }
        exit;
    }
    
    public function exec(){
        if(isset($this->curl_opt['help'])){
            $this->show_help();
        }

        if(isset($this->curl_opt['tenant_list'])){
            $this->show_tenant_list();
        }

        if(isset($this->curl_opt['facility_list']) && strlen($this->curl_opt['facility_list'])>0){
            $this->show_facility_list();
        }

        echo "Data Migration Initialize...\n\n";
        if(!isset($this->curl_opt['tenant']) || strlen($this->curl_opt['tenant'])==0 || !$this->set_tenant($this->curl_opt['tenant'])){
            print_r("Tenant not found! run --help to read more!");exit;
        }

        $facility_id = "";
        $facility_list = array();
        if(($result = $this->_db_query('SELECT id, name FROM tbase_facility where tenant_id="'.$this->tenant_id.'" order by id'))){
            foreach($result as $row){
                $facility_list[$row['id']] = $row['id'];
            }
        }
        if(isset($this->curl_opt['facility']) && strlen($this->curl_opt['facility'])>0){
            if(array_key_exists($this->curl_opt['facility'], $facility_list)!==FALSE){
                $facility_id = $this->curl_opt['facility'];
            }else{
                print_r("Facility not found! run --help to read more!");exit;
            }
        }else{
            if(sizeof(facility_list)>0){
                print_r("Please select one facility to continue.\n");
                $this->show_facility_list();
                exit;
            }else{
                $facility_id = array_shift($facility_list);
            }
        }

        $migrate_type = "";
        $file_path = "";
        if(isset($this->curl_opt['patient']) && file_exists($this->curl_opt['patient'])){
            $migrate_type = "patient";
            $file_path = $this->curl_opt['patient'];
            echo "Patient data migration";
        }else if(isset($this->curl_opt['encounter']) && file_exists($this->curl_opt['encounter'])){
            $migrate_type = "encounter";
            $file_path = $this->curl_opt['encounter'];
            echo "Encounter data migration";
        }else{
            print_r("Please specify migration type. e.g.: patient or encounter. run --help to read more!");exit;
        }

        $this->facility_id = $facility_id;
        $this->migrate_type = $migrate_type;
        $this->allow_skip = 0;
        $this->error_abort = 1;
        $this->argv['--file']  = $file_path;
        $this->real_filename = basename($file_path);
        if(isset($this->curl_opt['skip-error'])){
            echo " with skip-error option";
            $this->allow_skip = 1;
            $this->error_abort = 0;
        }

        $trial_result = false;
        $data = false;
        echo ", test upload and gathering info...\n\n";
        $func   = "migrate_".$this->migrate_system."_".$this->migrate_type;
        if (method_exists($this, $func)) {
            $trial_result = call_user_func(array($this, $func), true);
            if($trial_result['process_end']==1){
                $data = $trial_result['data'];
            }
            unset($trial_result['data']);

            echo "Test Result:\n\n";
            if(!isset($this->curl_opt['verbose'])){
                unset($trial_result['error_list']);
            }
            print_r($trial_result);
        }
        $this->show_usedtime();

        $proceed_status = false;
        if(is_array($data) && sizeof($data)>0){
            if(isset($this->curl_opt['proceed'])){
                $proceed_status = true;
            }else{
                $confirmation = "";
                do{
                    $message   =  "\n\nAre you sure you want to migrate now? [y/n]\n";
                    print $message;
                    flush();
                    $confirmation  =  strtolower(trim( fgets( STDIN ) ));
                    if ( $confirmation === 'y' ) {
                       $proceed_status = true;
                    }
                }while(array_search($confirmation,array('y','n'))===FALSE);
            }
        }else{
            echo ", error found...\n\n";
        }

        $trial_result = false;
        if($proceed_status){
            $this->set_usedtime();
            echo ", processing now...\n\n";
            $func   = "migrate_proceed_".$this->migrate_system."_".$this->migrate_type;
            if (method_exists($this, $func)) {
                if(($trial_result = call_user_func(array($this, $func), $data))){
                    echo "Result: Success";
                }else{
                    echo "Result: Fail";
                }
                echo "\n\n";
            }
        }

        echo "process end.\n\n";
    }
}

$class = new data_migration;
$class->exec();
