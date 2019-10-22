<?php
if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

$app_path = substr(__DIR__,0,(stripos(__DIR__,DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR) + 5));include($app_path.'../env.php');if($session_storage=='DB'){include_once($app_path.'assets_global/aws/SeesionStorageToDynamoDB.php');session_name("MIMSCIS");session_start();}

require_once dirname(__FILE__).'/../multitenant/Classes/drugMainClass.php';

class ins_invoice extends drugMainClass{
    var $curl_opt = array();
    var $email_list = array('jacky.low@mims.com');
    
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
        print_r("\nUsed Time: ".(($htime>0)?$htime." Hour".(($htime>1)?"s ":""):"").(($mtime>0)?$mtime." Minute".(($mtime>1)?"s ":""):"").$stime." Second".(($stime>1)?"s ":"")."\n\n");
        $this->start_time = 0;
    }
    
    public function init(){
        $this->curl_opt = getopt('', array('tenant_list','facility_list','insurance_list','breakdown','tenant::','facility::','insurance::','export_name::','date_from::','date_to::','help','sendmail::','delete_on_send'));
    }
    
    public function show_help(){
        if(true){
            echo'
Description:
* insurance invoice export.

list of commands:

> php ins_invoice.php --tenant_list
        - show tenant list

> php ins_invoice.php --tenant="tenant_id" --facility_list
        - show facility list e.g: id::name

> php ins_invoice.php --tenant="tenant_id" --insurance_list
        - show insurance list e.g: id::name

> php ins_invoice.php --export_name="file_name"
        - export to the chosen file_name. Default filename as timestamp.

> php ins_invoice.php --tenant="tenant_id"
	- used to proceed the monthly export for selected tenant and default branch.

> php ins_invoice.php --tenant="tenant_id" --breakdown
	- used to proceed the monthly export for selected tenant with breakdown.

> php ins_invoice.php --tenant="tenant_id" --facility="branch_id"
	- used to proceed the monthly export for selected tenant and selected branch.

> php ins_invoice.php --tenant="tenant_id" --insurance="1,2,3"
	- used to proceed the monthly export for selected tenant and selected insurance 1,2,3.

> php ins_invoice.php --tenant="tenant_id" --sendmail
	- used to proceed the monthly export for selected tenant and send to email. Seperate by comma. Default will be use if not value given.

> php ins_invoice.php --tenant="tenant_id" --facility="branch_id" --date_from="2016-12-01" --date_to="2016-12-07"
	- used to proceed the export between 2016-12-01 and 2016-12-07 for selected tenant and selected branch.

> php ins_invoice.php --help
	- used to show help menu.

';
            exit;
        }
    }

    public function sendmail($email=array(),$msg_body="",$subject="",$attachment=array()){
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
        foreach($attachment as $a){
            $mail->AddAttachment($a);
        }
        $mail->send();
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

    public function show_insurance_list($tenant_id=''){
        if($tenant_id=='' && isset($this->curl_opt['tenant'])){
            $tenant_id = $this->curl_opt['tenant'];
        }
        if(($result = $this->_db_query('SELECT id insurance_id,name FROM tbase_insurance_companies WHERE tenant_id="'.$tenant_id.'" ORDER BY name'))){
            print_r($result);
        }else{
            print_r("Insurance not found! run --help to read more!");
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

        if(isset($this->curl_opt['facility_list'])){
            $this->show_facility_list();
        }

        if(isset($this->curl_opt['insurance_list'])){
            $this->show_insurance_list();
        }

        echo "Process Initialize...\n\n";
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

        $insurance_list = array();
        if(isset($this->curl_opt['insurance']) && strlen($this->curl_opt['insurance'])>0){
            $temp = preg_replace('#[^0-9,]#iu', '', $this->curl_opt['insurance']);
            $insurance_list = explode(",",$temp);
        }

        $date_from = "";
        $date_to = "";
        $filename = "";
        if(isset($this->curl_opt['date_from'])){
            $date_from = $this->curl_opt['date_from'];
        }else{
            $date_from = date("Y-m-01");
        }
        if(isset($this->curl_opt['date_to'])){
            $date_to = date("Y-m-d 23:59:59",strtotime($this->curl_opt['date_to']));
        }else{
            $date_to = date("Y-m-d 23:59:59",strtotime('last day of this month',strtotime($date_from)));
        }
        if(isset($this->curl_opt['export_name'])){
            $filename = $this->curl_opt['export_name'];
        }else{
            $filename = $this->tenant_id."_".$facility_id."_ins_".date("YmdHis");
        }

        $this->facility_id = $facility_id;
        $this->date_from = $date_from;
        $this->date_to = $date_to;
        $this->export_filename  = $filename;

        if($query_result = sqlQuery('SELECT * FROM tenants WHERE tenant_id=? LIMIT 1',array($this->tenant_id))){
            $_SESSION['site_id'] = $query_result['name'];
        }
        $_SESSION['authUserID'] = "0";

        $breakdown = 0;
        if(isset($this->curl_opt['breakdown'])){
            $breakdown = 1;
        }

        $ignoreAuth = 1;
        include_once dirname(__FILE__).'/../assets_global/PHPExcel/Classes/PHPExcel.php';
        include_once dirname(__FILE__).'/../assets_global/PHPExcel/Classes/PHPExcel/Calculation.php';
        include dirname(__FILE__).'/../interface/reports/list-insurance-invoice/liiModel.php';
        include dirname(__FILE__).'/../interface/reports/common/commonReportFunctions.php';
        $ins_list = LIIModel::getInsCorp();
        $excel_list = array();
        foreach($ins_list as $key => $value){
            if(sizeof($insurance_list)>0 && array_search($key, $insurance_list)===FALSE){
                continue;
            }
            $report_data = LIIModel::generateReport($date_from, $date_to, $key, $facility_id, $breakdown);
            if(sizeof($report_data['records'])==0){continue;}
            $excel_data = base64_encode(json_encode($report_data['excelData']));
            ob_start();
            CommonReportFunctions::downloadExcelInvoiceReport($excel_data);
            $response = ob_get_clean();
            $temp = $value.".xlsx";
            $excel_list[$temp] = $response;
        }
        if(sizeof($excel_list)>0){
            echo "Number of file: ".sizeof($excel_list)."\n\n";
            echo "Creating Zip File...\n\n";
            $file_path = str_replace(array("/","\\"),"",$filename.".zip");
            $zip = new ZipArchive();
            if ($zip->open($file_path, ZipArchive::CREATE)!==TRUE) {
                exit("cannot open <$file_path>\n");
            }
            foreach($excel_list as $key => $value){
                $zip->addFromString($key, $value);
            }
            $zip->close();

            if(isset($this->curl_opt['sendmail'])){
                echo "Sending Email...\n\n";
                $email_list = $this->email_list;
                if(!empty($this->curl_opt['sendmail']) && strlen($this->curl_opt['sendmail'])>0){
                    $email_list = explode(",",$this->curl_opt['sendmail']);
                }
                $title = $this->tenant_name.(($breakdown==1)?" Breakdown":"")." Invoices"." From ".date("Y-m-d",strtotime($date_from))." To ".date("Y-m-d",strtotime($date_to));
                $this->sendmail($email_list, $title, $title, array($file_path));
                if(isset($this->curl_opt['delete_on_send'])){
                    echo "Delete file once email sent...\n\n";
                    unlink($file_path);
                }
            }
        }else{
            echo "Data not found.\n\n";
        }
        
        echo "process end.\n\n";
    }
}

$class = new ins_invoice;
$class->exec();
