<?php

if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

$temp = exec('ps -ef | grep "php maintenance_cron.php" | grep -v "grep" -c',$tmp1,$tmp2);
if((int)$tmp2 == 0 && (int)$temp==1){
}else{die("Process running! Exit.");}

require_once dirname(__FILE__).'/../multitenant/Classes/dataMigrationClass.php';

class maintenance_cron extends dataMigrationClass{

    var $selected_task_id = 0;
    var $start_time = "01:00:00";
    var $stop_time = "05:30:00";

    public function __construct() {
        parent::__construct();
    }

    public function exec(){
        $maintenance_file = dirname(__FILE__).'/../../maintenance.enable';
        $start_time = strtotime(date("Y-m-d ".$this->start_time));
        $end_time = strtotime(date("Y-m-d ".$this->stop_time));
        if($start_time > $end_time){
            $start_time = strtotime('-1 day',strtotime($start_time));
        }

        do{
            $cur_time = strtotime('now');
            if($cur_time<$start_time || $cur_time>$end_time){
                if(file_exists($maintenance_file)){
                    if($this->_db_query('SELECT id FROM data_migration_list WHERE dm_status=1 LIMIT 1')){
                        sleep(60);
                        continue;
                    }
                    unlink($maintenance_file);
                }
                break;
            }
            if(!file_exists($maintenance_file)){
                file_put_contents($maintenance_file,"");
            }
        }while(true);
        
    }
}

$class = new maintenance_cron;
$class->exec();
