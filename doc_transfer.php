<?php
require(dirname(__FILE__).'/../multitenant/Classes/drugMainClass.php');
require(dirname(__FILE__).'/../multitenant/Classes/s3Class.php');
$s3class = new s3Class;
$mainclass = new drugMainClass;

$bucket_from = 'stat.mimscis.com';
$bucket_to = 'stat.mimscis.com';
/*
$tenant_list = array();
if(($result = $mainclass->_db_query('SELECT tenant_id FROM tbase_documents WHERE url LIKE "file:///var/www/html%" GROUP BY tenant_id'))){
    foreach($result as $row){
        $tenant_list[$row['tenant_id']] = $row['tenant_id'];
    }
}
*/
$tenant_list = array('medilove'=>'medilove');

foreach($tenant_list as $tenant_id){
    $class = new drugMainClass;
    if($class->set_tenant($tenant_id)){
        if(($result = sqlStatement('select a.id,a.date,a.url,a.mimetype,a.size,a.mimetype,a.imported,a.tenant_id,p.pid,b.id as facility_id,b.facility_npi
            ,if(ifnull(d9.encounter,0)>0,d9.encounter,ifnull(d1.encounter,ifnull(d3.encounter,""))) encounter_id
            from tbase_documents a
            join tbase_patient_data p on a.tenant_id=p.tenant_id and a.foreign_id=p.pid
            join tbase_facility b on a.tenant_id=b.tenant_id and p.facility_id=b.id
            left join tbase_facility c on a.tenant_id=c.tenant_id and p.facility_id=b.id and b.id>c.id
            left join tbase_form_encounter d1 on a.tenant_id=d1.tenant_id and a.foreign_id=d1.pid and (cast(d1.date as date)<=cast(a.date as date))
            left join tbase_form_encounter d2 on a.tenant_id=d2.tenant_id and a.foreign_id=d2.pid and (cast(d2.date as date)<=cast(a.date as date)) and d1.id>d2.id
            left join tbase_form_encounter d3 on a.tenant_id=d3.tenant_id and a.foreign_id=d3.pid
            left join tbase_form_encounter d4 on a.tenant_id=d4.tenant_id and a.foreign_id=d4.pid and d3.id>d4.id
            left join tbase_form_encounter d9 on a.tenant_id=d9.tenant_id and a.foreign_id=d9.pid and a.encounter_id=d9.encounter
            where d2.id is null AND d4.id is null AND c.id is null AND ifnull(d1.encounter,ifnull(d3.encounter,""))<>"" AND a.url like "file:///var/www/html%" '))){
            while($patient_data = sqlFetchArray($result)){
                $imported = $patient_data['imported'];
                $encounter_id = $patient_data['encounter_id'];
                $pid = $patient_data['pid'];
                $facility_id = $patient_data['facility_id'];
                $file_size = $patient_data['size'];
                $file_type = $patient_data['mimetype'];
                $file_date = $patient_data['date'];
                $file_id = $patient_data['id'];
                $url = $patient_data['url'];
                $filepath = preg_replace("|^(.*)://|","",$url);
                $file_name = basename($filepath);
                $pubpid = $patient_data['facility_npi'].'-'.$patient_data['pid'];
                $patient_doc_path = 'patient_documents/'.$tenant_id.'/'.$pubpid.'/'.$file_name;
                
                if($data = $s3class->bucket_get_info($filepath,$bucket_from)){
                    if($s3class->bucket_put_data($data->body, $patient_doc_path, $data->headers['type'], $bucket_to)){
                        sqlStatement('UPDATE documents SET imported=? WHERE id=?',array($encounter_id,$file_id));
                        $imported = $encounter_id;
                    }
                }
                
                if((int)$imported>0){
                    if(!sqlQuery('SELECT id FROM encounter_documents WHERE encounter_id=? AND pid=? AND facility_id=? AND file_original_name=?',array($encounter_id,$pid,$facility_id,$file_name))){
                        sqlStatement('INSERT INTO encounter_documents SET encounter_id=?, pid=?, facility_id=?, file_name=?, file_original_name=?, file_size=?, file_type=?, uploaded_at=?, status=?',array($encounter_id,$pid,$facility_id,$file_name,$file_name,$file_size,$file_type,$file_date,'1'));
                    }
                }
                
            }
        }
    }
}
/*delete info*/
//$mainclass->_db_execute('DELETE FROM tbase_categories_to_documents');
