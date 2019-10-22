<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once dirname(__FILE__).'/../multitenant/Classes/drugMainClass.php';

class migrate extends drugMainClass{
    var $sql_folder = '';
    var $current_version = array();
    var $force = false;
    var $charset = "utf8";
    var $curl_opt = array();
    
    public function __construct() {
        parent::__construct();
        $this->init();
    }
    
    public function init(){
        $this->sql_folder = dirname(__FILE__).'/../../offline/sql/';
        $this->curl_opt = getopt('', array('version::','skip-error','charset::','from::','to::','help'));
    }
    
    public function exec(){
        if(isset($this->curl_opt['help'])){
            echo'Update DB Migration (migrate.php)

Description: 
* store the sql file in *offline/sql/[version-number]*
* put list.txt file to store the sequence of query execution
* create a script to do db initialization

list of commands:

a) php migrate.php
	- used to initialize db.
	
b) php migrate.php --version="v1.0.0"
	- used to migrate only specified version.
	
c) php migrate.php --charset="utf8"
	- used to set default charset.
	- default is "utf8".
	
d) php migrate.php --skip-error
	- used to skip error and continue running the migration.
	
e) php migrate.php --from="v1.0.0" --to="v1.0.5"
	- used to migrate the db start from version v1.0.0 to v1.0.5.

Usage/Test Case:

1. Initialise DB
	> php migrate.php
	
2. Initialise DB by ignore the error
	> php migrate.php --skip-error
	
3. Migrate DB from v1.0.5 and onwards by ignore the error
	> php migrate.php --from="v1.0.5" --skip-error
	
4. Migrate DB from beginning to v1.1.0 only
	> php migrate.php --to="v1.1.0"
	
5. Migrate DB start from v1.0.0 to v1.1.1 only by ignore the error
	> php migrate.php --from="v1.0.0" --to="v1.1.1" --skip-error

';
            exit;
        }
        
        if(isset($this->curl_opt['skip-error'])){
            $this->force = true;
            echo "MySQL Ignore Error = ON\n";
        }else{
            echo "MySQL Ignore Error = Off\n";
        }
        if(isset($this->curl_opt['charset']) && strlen($this->curl_opt['charset'])>0){
            $this->charset = $this->curl_opt['charset'];
        }
        echo "MySQL default charset = ".$this->charset."\n\n";

        if(isset($this->curl_opt['from']) && (strlen($this->curl_opt['from'])==0 || !file_exists($this->sql_folder.$this->curl_opt['from']))){
            die("--from version folder not found!");
        }
        if(isset($this->curl_opt['to']) && (strlen($this->curl_opt['to'])==0 || !file_exists($this->sql_folder.$this->curl_opt['to']))){
            die("--to version folder not found!");
        }
        
        $this->_db_conn();
        if($this->_db_query('SELECT * FROM information_schema.tables WHERE table_schema="'.$this->db['name'].'" AND table_name="migrate_version" LIMIT 1')){
            $query = 'SELECT version,sql_file FROM migrate_version WHERE update_type="db_migrate" GROUP BY version,sql_file ORDER BY update_at DESC';
            if(($temp = $this->_db_query($query))){
                foreach($temp as $d){
                    $name = $d['version']."_".$d['sql_file'];
                    $this->current_version[$name] = $name;
                }
            }
        }
        
        if(isset($this->curl_opt['version']) && strlen($this->curl_opt['version'])>0){
            $folder = $this->sql_folder.$this->curl_opt['version'];
            if(!file_exists($folder)){
                die("Version folder not found!");
            }
            if($this->import_process($folder)){}
        }else{
            $folder_list = glob($this->sql_folder.'v*',GLOB_ONLYDIR);
            sort($folder_list,SORT_NATURAL);
            $start_from_status = false;
            foreach($folder_list as $folder){
                if(isset($this->curl_opt['from']) && !$start_from_status){
                    if(strtolower($this->curl_opt['from'])==strtolower(basename($folder))){
                        $start_from_status = true;
                    }else{
                        continue;
                    }
                }
                if($this->import_process($folder)){}
                if(isset($this->curl_opt['to']) && strtolower($this->curl_opt['to'])==strtolower(basename($folder))){
                    break;
                }
            }
        }
        
        echo "Migration end.\n\n";
    }
    
    private function import_process($folder){
        $version = basename($folder);
        
        $error = 0;
        $charset = $this->charset;
        $force = $this->force;
        $list_path = preg_replace('#/[/]+#iu','/',$folder.'/list.txt');
        
        $temp_list = array();
        if(file_exists($list_path)){
            $list = file($list_path);
            if(sizeof($list)>0){
                foreach($list as $sql_file){
                    if(strlen(trim($sql_file))>0){
                        $name = $version."_".trim($sql_file);
                        $temp_list[$name] = $sql_file;
                    }
                }
            }
        }
        
        $active_list = array_diff_key($temp_list,$this->current_version);
        if(sizeof($active_list)==0){
            echo $version." skip or updated.\n";
            return false;
        }
        
        echo $version." migrate start.\n";
        
        foreach($active_list as $sql_file){
            $sql_file = trim($sql_file);
            echo "Try to migrate ".$sql_file."......";
            
            $sql_path = preg_replace('#/[/]+#iu','/',$folder.'/'.$sql_file);
            if(file_exists($sql_path)){
                $this->_db_conn ();
                $extra_options = array();
                if($force){
                    $extra_options[] = "-f";
                }
                $cmd = $this->mysql_path.' '.implode(" ",$extra_options).' --default-character-set="'.$charset.'" --host="'.$this->db['host'].'" --user="'.$this->db['user'].'" --password="'.$this->db['pass'].'" '.$this->db['name'].' < '.$sql_path;
                exec($cmd,$var2,$var3);
                if($var3!=0){
                    $error += 1;
                    echo "Fail!\n";
                    continue;
                }else{
                    echo "Done.\n";
                }
            }else{
                echo "File not found \n";
                continue;
            }
            $query = 'INSERT INTO migrate_version SET version="'.$version.'",sql_file="'.$sql_file.'",update_type="db_migrate",update_by="'.get_current_user().'",update_at="'.date("Y-m-d H:i:s").'"';
            $this->_db_execute($query);
        }
        
        if($error==0){
            echo $version." migrate successfully.\n\n";
            return true;
        }
        echo $version." migrate fail.\n\n";
        return false;
    }
}

$class = new migrate;
$class->exec();
