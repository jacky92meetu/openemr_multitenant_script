<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

require_once dirname(__FILE__).'/../multitenant/Classes/dynamoDBClass.php';

class aws extends dynamoDBClass{
    var $curl_opt = array();

    public function __construct() {
        parent::__construct();
        $this->init();
    }

    public function init(){
        $this->curl_opt = getopt('', array('copy-sites','delete-session','help','export','export-from::','import::','import-to::'));
    }

    public function show_help(){
        if(true){
            echo'AWS command

Description:
* Please set crontab for session garbage collection.

list of commands:

php aws.php --copy-sites
	- used to copy sites/tenants db array to dynamodb

php aws.php --export
	- used to export sites/tenants data from dynamodb

php aws.php --export-from="tenant_data"
	- used to export sites/tenants data from dynamodb

php aws.php --import="file_location"
	- used to import sites/tenants data to dynamodb

php aws.php --import="file_location" --import-to="tenant_data"
	- used to import sites/tenants data to tenant_data table in dynamodb

php aws.php --delete-session
	- used to session garbage collection.

php aws.php --help
	- used to show help menu.

';
            exit;
        }
    }

    public function exec(){


        if(isset($this->curl_opt['copy-sites'])){
            echo "AWS dynamoDB sites copy start...\n\n";
            $this->copy_site();
        }else if(isset($this->curl_opt['delete-session'])){
            echo "AWS dynamoDB session garbage collect start...\n\n";
            $this->session_garbage_collect();
        }else if(isset($this->curl_opt['export'])){
            echo "AWS dynamoDB tenants export start...\n\n";
            $this->export();
        }else if(isset($this->curl_opt['export-from']) && strlen($this->curl_opt['export-from'])>0){
            echo "AWS dynamoDB tenants export start...\n\n";
            $this->export($this->curl_opt['export-from']);
        }else if(isset($this->curl_opt['import']) && file_exists($this->curl_opt['import'])){
            echo "AWS dynamoDB tenants import start...\n\n";
            if(isset($this->curl_opt['import-to']) && strlen($this->curl_opt['import-to'])>0){
                $this->import($this->curl_opt['import-to'],$this->curl_opt['import']);
            }else{
                $this->import($this->curl_opt['import']);
            }
        }else{
            $this->show_help();
        }

        echo "AWS process end.\n\n";
    }
}

$class = new aws;
$class->exec();
