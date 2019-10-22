<?php

if(php_sapi_name() !== 'cli'){
    die("Please run from CLI.");
}

$opt = getopt('', array('facility::','url-only::','put-to-s3-only::'));
if(empty($opt['facility']) && empty($opt['url-only']) && empty($opt['put-to-s3-only'])){
    echo'Data Export Function command

Description:
* This is a backup function that used to export facilities data and attachments.
* --facility OR --url-only is a must.

list of commands:

php data_export_func.php --facility="facility_id"
    - export basic data.
	
php data_export_func.php --facility="facility_id" --path="/tmp/"
    - change to valid path, "/tmp/" is the default folder.

php data_export_func.php --facility="facility_id" --alldata
    - export all data.

php data_export_func.php --facility="facility_id" --attachment
    - export data with attachments. By default the system will use linux zip function. Unless --attachment="php" will set to use PHP zipArchive library.

php data_export_func.php --facility="facility_id"  --attachment-only
    - only export attachments;

php data_export_func.php --facility="facility_id" --exclude="panel_list.xlsx,patient_info.xlsx"
    - export data except panel_list.xlsx and patient_info.xlsx.

php data_export_func.php --facility="facility_id" --include="panel_list.xlsx,patient_info.xlsx"
    - only export panel_list.xlsx and patient_info.xlsx.
	
php data_export_func.php --email="abc@abc.com"
    - send email once process done.

php data_export_func.php --to-s3="bucket"
    - put to S3 bucket. default is "prod-cis-downloadable".

php data_export_func.php --signed-url-expire="3600"
    - generate S3 signed url with expire time set to 3600 sec. default is 1 year.

php data_export_func.php --url-only="path"
    - ONLY generate S3 signed url.

php data_export_func.php --put-to-s3-only="file"
    - ONLY put the file into s3.

php data_export_func.php --tmp-path="path"
    - Change the export path. default is system defined TEMP folder.

php data_export_func.php --no-compress
    - Generate report without compress.

php data_export_func.php --memory-limit="2048MB"
    - Set PHP memory limit.

php data_export_func.php --query-limit="0,500"
    - Set data rows start from 0 position and limit the data size to 500 rows.

php data_export_func.php --filename-prefix="F01"
    - Set the filename prefix.

php data_export_func.php --max-row="50000"
    - Set the max data row per file. Default value is 50000.

php data_export_func.php --multi-thread=4
    - [ LINUX SERVER ONLY ] Multiple thread process. It is no stable and might cause serious problem.

';
    exit;
}

#file format: xlsx, csv
$data_list = array(
    'panel_list.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.name,'') 'Insurance Name', IFNULL(a.attn,'') 'Insurance Attn', IFNULL(b.line1,'') 'Insurance Addr1', IFNULL(b.line2,'') 'Insurance Addr2'
        , IFNULL(b.city,'') 'Insurance City', IFNULL(b.state,'') 'Insurance State', IFNULL(b.zip,'') 'Insurance Postcode', IFNULL(c.number,'') 'Insurance Contact'
        from insurance_companies a
        left join addresses b on IFNULL(b.foreign_id,'') = IFNULL(a.id,'')
        left join phone_numbers c on IFNULL(c.foreign_id,'') = IFNULL(a.id,'')
        ",
    'patient_info.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID',  IFNULL(a.fname,'') 'First Name', IFNULL(a.lname,'') 'Last Name', IFNULL(a.DOB,'') 'DOB', IFNULL(a.street,'') 'Address', IFNULL(a.postal_code,'') 'Postal Code', IFNULL(a.city,'') 'City', IFNULL(a.state,'') 'State', IFNULL(a.country_code,'') 'Country'
        , IFNULL(a.ss,'') 'Passport', IFNULL(a.employee_id,'') 'Employee ID', IFNULL(a.phone_home,'') 'Home Phone', IFNULL(a.contact_relationship,'') 'Emergency Contact Name', IFNULL(a.phone_contact,'') 'Emergency Contact No.'
        ,IFNULL(a.phone_cell,'') 'Mobile Phone', IFNULL(a.status,'') 'Marital Status', IFNULL(a.date,'') 'Created Date', IFNULL(a.sex,'') 'Gender', IFNULL(a.email,'') 'Email', IFNULL(a.race,'') 'Race', IFNULL(a.monthly_income,'') 'Monthly Income', IFNULL(a.pubpid,'') 'Patient Long ID'
        ,IFNULL(a.pid,'') 'Patient ID', IFNULL(a.is_active,'') 'Active', IFNULL(a.genericname1,'') 'Remarks', IFNULL(a.Religion,'') 'Religion', IFNULL(a.deceased_date,'') 'Deceased Date', IFNULL(a.deceased_reason,'') 'Deceased Reason'
        , IFNULL(a.occupation,'') 'Occupation', ifnull(b.name,'') 'Employer Name', IFNULL(b.street,'') 'Employer Address', IFNULL(b.postal_code,'') 'Employer Postal', IFNULL(b.city,'') 'Employer City', IFNULL(b.state,'') 'Employer State'
        , IFNULL(b.country,'') 'Employer Country', IFNULL(c.username,'System') 'Created By'
        from patient_data a
        left join employer_data b on b.pid=a.pid
        left join users_secure c on c.id=a.created_by",
    'patient_allergy.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.pid,'') 'Patient ID', TRIM(CONCAT(IFNULL(b.fname,''),' ',IFNULL(b.lname,''))) 'Patient Name'
        ,IFNULL(a.drug_name,'') 'Drug Name', IFNULL(a.brand_name,'') 'Drug Brand', IFNULL(a.allergy_effect,'') 'Allergy Effect', IFNULL(a.allergy_record_date,'') 'Created Date', IFNULL(c.username,'System') 'Created By'
        from patient_allergy_list a
        join patient_data b on b.pid=a.pid
        left join users_secure c on c.id=a.created_by
        ",
    'patient_alert.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.pid,'') 'Patient ID', TRIM(CONCAT(IFNULL(b.fname,''),' ',IFNULL(b.lname,''))) 'Patient Name'
        ,IFNULL(a.alert,'') 'Alert and Clinical History', IFNULL(a.added_on,'') 'Created Date', IFNULL(c.username,'System') 'Created By'
        from patient_alerts a
        join patient_data b on b.pid=a.pid
        left join users_secure c on c.id=a.added_by
        ",
    'patient_ins.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.pid,'') 'Patient ID', TRIM(CONCAT(IFNULL(b.fname,''),' ',IFNULL(b.lname,''))) 'Patient Name'
        , IFNULL(a.type,'') 'Insurance Type', IFNULL(a1.name,'') 'Insurance Name', IFNULL(a.plan_name,'') 'Plan Name', IFNULL(a.date,'') 'Effective Date', IFNULL(a.group_number,'') 'Expiry Date'
        , IFNULL(a.policy_number,'') 'Policy Number', IFNULL(a.credit_limit,'') 'Credit Limit', IFNULL(a.remarks,'') 'Remark', IFNULL(a.subscriber_relationship,'') 'Relationship'
        , IFNULL(a.subscriber_fname,'') 'First Name', IFNULL(a.subscriber_lname,'') 'Last Name', IFNULL(a.subscriber_ss,'') 'Passport', IFNULL(a.subscriber_DOB,'') 'DOB'
        , IFNULL(a.subscriber_street,'') 'Street', IFNULL(a.subscriber_postal_code,'') 'Postal Code', IFNULL(a.subscriber_city,'') 'City', IFNULL(a.subscriber_state,'') 'State'
        , IFNULL(a.subscriber_country,'') 'Country', IFNULL(a.subscriber_phone,'') 'Contact No'
        from insurance_data a
        join insurance_companies a1 on a1.id=a.provider
        join patient_data b on b.pid=a.pid
        where IFNULL(a.type,'')<>'removed' and IFNULL(a.provider,'')<>''
        ",
    'patient_family.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.pid,'') 'Patient ID', TRIM(CONCAT(IFNULL(b.fname,''),' ',IFNULL(b.lname,''))) 'Patient Name'
        , IFNULL(a.relationship,'') 'Relationship' , IFNULL(a2.pid,'') 'Relationship ID', TRIM(CONCAT(IFNULL(b2.fname,''),' ',IFNULL(b2.lname,''))) 'Relationship Name'
        from tbase_family_card_member a
        join tbase_patient_data b on b.tenant_id=a.tenant_id and b.pid=a.pid
        join tbase_family_card_member a2 on a2.relationship='MASTER' and a2.tenant_id=a.tenant_id and a2.family_card_id=a.family_card_id
        join tbase_patient_data b2 on b2.tenant_id=a.tenant_id and b2.pid=a2.pid
        where a.tenant_id='{tenant_id}' and a.relationship<>'MASTER'",
    'users_list.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.username,'') 'Username', IFNULL(a.fname,'') 'First Name', IFNULL(a.lname,'') 'Last Name', IFNULL(a.active,'') 'Active', IFNULL(a.email,'') 'Email',IFNULL(a.is_locum,'') 'Is Locum' from users a
        join users_secure b on b.username=a.username
        where a.abook_type=''
        ",
);
$facility_list = array(
    'charges_list.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.code_text,'') 'Code Name', IFNULL(a.code,'') 'Shortcode', IF(a.code_type=110,'Doctor Charges','Other Charges') 'Charges Type'
        ,IFNULL(b.pr_price,0) 'Selfpay Price', IFNULL(b2.pr_price,0) 'Panel Price', IFNULL(a.cost_price,0) 'Cost Price'
        from tbase_codes a
        left join tbase_prices b on b.tenant_id=a.tenant_id and b.pr_id=a.id and b.is_drug=0 and b.pr_level='Selfpay'
        left join tbase_prices b2 on b2.tenant_id=a.tenant_id and b2.pr_id=a.id and b2.is_drug=0 and b2.pr_level='Insurance/Corporate'
        where a.active=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'charges_panel_price.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.charges_id,0) 'Code ID', IFNULL(a.insurance_id,0) 'Panel ID', IFNULL(a.price,0) 'Selling Price'
        from tbase_charges_ins_prices a
        join tbase_codes c on c.tenant_id=a.tenant_id and c.id=a.charges_id
        where c.active=1 and c.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'drug_list.xlsx'=>"select IFNULL(a.drug_id,'') 'DataRow ID', IFNULL(a.name,'') 'Drug Name', IFNULL(a.ndc_number,'') 'Drug Code', IFNULL(a.reorder_point,'') 'Reorder Quantity', IFNULL(a.allow_multiple,'0') 'Allow Multiple Lot'
        , IFNULL(a.notify_low_stock,'0') 'Notification on Low Qty', IFNULL(b.pr_price,0) 'Selfpay Price', IFNULL(b2.pr_price,0) 'Insurance Price', IFNULL(c.entity_id,'') 'MIMS Drugs Entity ID'
        , IFNULL(d.dosage,'') 'Default Dosage', IFNULL(e.title,'') 'Default Frequency', IFNULL(d.quantity,0) 'Default Quantity', IFNULL(d.patient_instruction,'') 'Instruction'
        from tbase_drugs a
        left join tbase_prices b on b.tenant_id=a.tenant_id and b.pr_id=a.drug_id and b.is_drug='1' and b.pr_level='Selfpay'
        left join tbase_prices b2 on b2.tenant_id=a.tenant_id and b2.pr_id=a.drug_id and b2.is_drug='1' and b2.pr_level='Insurance/Corporate'
        left join tbase_drugs_mims_drugs_map c on c.tenant_id=a.tenant_id and c.drug_id=a.drug_id
        left join tbase_drug_templates d on d.tenant_id=a.tenant_id and d.drug_id=a.drug_id
        left join tbase_list_options e on e.tenant_id=a.tenant_id and e.list_id='drug_interval' and e.option_id=cast(d.period as char)
        where a.active=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'drug_lot.xlsx'=>"select IFNULL(b.inventory_id,'') 'DataRow ID', IFNULL(b.drug_id,'') 'Drug ID', IFNULL(b.lot_number,'') 'Lot Number'
        , IFNULL(b.expiration,'') 'Expiry', IFNULL(b.on_hand,0) 'Quantity', IFNULL(b.warehouse_id,'') 'Warehouse', IFNULL(c.organization,'') 'Supplier'
        , IFNULL(b.manufacturer,'') 'Manufacturer'
        , ABS(IFNULL(d.fee,0)) 'Total Cost', IFNULL(d.sale_date,'') 'Transaction Date', IFNULL(d.notes,'') 'Remarks'
        from tbase_drugs a
        join tbase_drug_inventory b on b.tenant_id=a.tenant_id and b.drug_id=a.drug_id
        left join tbase_users c on c.tenant_id=a.tenant_id and c.abook_type='vendor' and c.id=b.vendor_id
        left join tbase_drug_sales d on d.tenant_id=a.tenant_id and d.inventory_id=b.inventory_id and d.prescription_id=0 and d.quantity<0 and d.writeoff=0
        left join tbase_drug_sales d2 on d2.tenant_id=a.tenant_id and d2.inventory_id=b.inventory_id and d2.prescription_id=0 and d2.quantity<0 and d2.writeoff=0 and d.sale_id>d2.sale_id
        where a.active=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'
        and d2.sale_id is null",
    'drug_sales.xlsx'=>"select IFNULL(b.sale_id,'') 'DataRow ID', IFNULL(b.drug_id,'') 'Drug ID', IFNULL(b.inventory_id,'') 'Inventory ID', IFNULL(b.prescription_id,'') 'Prescription ID'
        , IFNULL(b.encounter,0) 'Encounter ID'
        , CASE
                WHEN IFNULL(b.prescription_id,0)<>0 THEN 'Sale'
                WHEN IFNULL(b.xfer_inventory_id,0)<>0 THEN 'Transfer'
                WHEN IFNULL(b.distributor_id,0)<>0 THEN 'Distribution'
                WHEN IFNULL(b.writeoff,0)=1 THEN 'WriteOff'
                WHEN IFNULL(b.fee,0)<>0 THEN 'Purchase'
                ELSE 'Adjustment'
        END 'Sale Type'
        , ABS(IFNULL(b.quantity,0)) 'Quantity', ABS(IFNULL(b.fee,0)) 'Total Cost', IFNULL(b.sale_date,'') 'Transaction Date', IFNULL(b.notes,'') 'Remarks'
        from tbase_drugs a
        join tbase_drug_sales b on b.tenant_id=a.tenant_id and b.drug_id=a.drug_id
        where a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'drug_panel_price.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.drug_id,'') 'Drug ID', IFNULL(a.insurance_id,'') 'Insurance ID', IFNULL(a.insurance_name,'') 'Insurance Name', IFNULL(a.price,0) 'Price'
        from tbase_drug_ins_prices a
        join tbase_drugs b on b.tenant_id=a.tenant_id and b.drug_id=a.drug_id
        where b.active=1 and b.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'drug_supplier_list.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID', IFNULL(a.organization,'') 'Organization Name', IFNULL(a.title,'') 'Director Title', IFNULL(a.lname,'') 'Last Name', IFNULL(a.fname,'') 'First Name', IFNULL(a.mname,'') 'Middle Name'
        , IFNULL(a.phone,'') 'Home Phone', IFNULL(a.phonecell,'') 'Mobile Phone', IFNULL(a.phonew1,'') 'Office Phone1', IFNULL(a.phonew2,'') 'Office Phone2', IFNULL(a.fax,'') 'Fax'
        , IFNULL(a.assistant,'') 'Assistant', IFNULL(a.email,'') 'Email', IFNULL(a.email_direct,'') 'Trust Email', IFNULL(a.url,'') 'Website'
        , IFNULL(a.street,'') 'Address', IFNULL(a.streetb,'') 'Address2', IFNULL(a.city,'') 'City', IFNULL(a.state,'') 'State', IFNULL(a.zip,'') 'Postcode'
        , IFNULL(a.street2,'') 'Atl Address', IFNULL(a.streetb2,'') 'Atl Address2', IFNULL(a.city2,'') 'Atl City', IFNULL(a.state2,'') 'Atl State', IFNULL(a.zip2,'') 'Atl Postcode'
        , IFNULL(a.notes,'') 'Notes'
        from tbase_users a
        where a.tenant_id='{tenant_id}' and a.abook_type='vendor'",
    'encounter_list.xlsx'=>"select IFNULL(a.id,'') 'DataRow ID',IFNULL(a.date,'') 'Enc Date', IFNULL(a.reason,'') 'Reason', IFNULL(a.pid,'') 'Patient ID', IFNULL(a.encounter,'') 'Enc ID'
        , IFNULL(a.invoice_no,'') 'Invoice No', IF(IFNULL(a.pc_catid,10)=10,'Out Patient','OTC') 'Enc Type', IFNULL(d.username,'') 'Created By', IFNULL(a.paymenttype,'') 'Payment Type'
        , IFNULL(f.name,'') 'Panel Name', IFNULL(a.start_consulation,'') 'Start Consultation', IFNULL(a.end_consulation,'') 'End Consultation', IFNULL(a.chit_number,'') 'Chit Number'
        , IFNULL(a.created_at,a.date) 'Created At', group_concat(IFNULL(c.short_title,'')) 'Diagnosis'
        from tbase_form_encounter a ignore index(paymenttype)
        left join tbase_encounter_diagnosis b on b.tenant_id=a.tenant_id and b.encounter=a.encounter
        left join tbase_custom_diagnosis c on c.tenant_id=a.tenant_id and c.id=b.custom_diagnosis_id
        left join tbase_users d on d.tenant_id=a.tenant_id and d.id=a.provider_id
        left join tbase_insurance_data e on e.tenant_id=a.tenant_id and e.id=a.insurance_id
        left join tbase_insurance_companies f on f.tenant_id=a.tenant_id and f.id=e.provider
        where a.encounterstatus=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'
        group by a.encounter",
    'encounter_vitals.xlsx'=>"select IFNULL(c.id,'') 'DataRow ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(a.pid,'') 'Patient ID', IFNULL(c.date,'') 'Date', IFNULL(c.weight,'') 'Weight (kg)', IFNULL(c.height,'') 'Height (cm)'
        , IFNULL(c.BMI,'') 'BMI (kg/m^2)', IFNULL(c.BMI_status,'') 'BMI Status' , IFNULL(c.bps,'') 'BP Systolic (mmHg)', IFNULL(c.bpd,'') 'BP Diastolic (mmHg)', IFNULL(c.pulse,'') 'Pulse (per min)'
        , IFNULL(c.respiration,'') 'Respiration (per min)', IFNULL(c.temperature,'') 'Temperature (C)', IFNULL(c.temp_method,'') 'Temp Location', IFNULL(c.head_circ,'') 'Head Circumference (cm)', IFNULL(c.note,'') 'Notes', IFNULL(b.user,'') 'Created By'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_forms b on b.tenant_id=a.tenant_id and b.pid=a.pid and b.encounter=a.encounter and b.form_name='Vitals'
        join tbase_form_vitals c on c.tenant_id=a.tenant_id and c.id=b.form_id
        where a.encounterstatus=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'encounter_prescription.xlsx'=>"select IFNULL(b.id,'') 'DataRow ID', IFNULL(b.id,'') 'Prescription ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(a.pid,'') 'Patient ID', IFNULL(b.drug_id,'') 'Drug ID', IFNULL(b.drug,'') 'Drug Name'
        , IFNULL(b.aou,'') 'Action', IFNULL(b.dosage,'') 'Dosage', IFNULL(b.uou,'') 'Unit', IFNULL(b.rou,'') 'Route', IFNULL(c.title,'') 'Frequency', IFNULL(b.duration,'') 'Duration'
        , IFNULL(b.quantity,'') 'Quantity', IFNULL(b.note,'') 'Patient Instruction', IFNULL(d.username,'') 'Created By'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_prescriptions b on b.tenant_id=a.tenant_id and b.patient_id=a.pid and b.encounter=a.encounter
        left join tbase_list_options c on c.tenant_id=a.tenant_id and c.list_id='drug_interval' and c.option_id=cast(b.`interval` as char)
        left join tbase_users d on d.tenant_id=a.tenant_id and d.id=b.user
        where a.encounterstatus=1 and b.active=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'encounter_billing.xlsx'=>"select IFNULL(b.id,'') 'DataRow ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(a.pid,'') 'Patient ID', IFNULL(b.bill_no,'') 'Bill Number', IFNULL(b.code_type,'') 'Item Type'
        , IFNULL(b.code_text,'') 'Item Name', IFNULL(b.code,'') 'Item Code', IFNULL(b.paymenttype,'') 'Payment Type', IFNULL(b.units,'') 'Quantity', IFNULL(b.unit_fee,'') 'Unit Price'
        , IFNULL(b.selfpay_bill_no,'') 'Selfpay Bill', IFNULL(b.selfpay_amount,'') 'Selfpay Amount', IFNULL(b.selfpay_taxrate,'') 'Selfpay Tax'
        , IFNULL(b.inspay_bill_no,'') 'Panel Bill', IFNULL(b.inspay_amount,'') 'Panel Amount', IFNULL(b.inspay_taxrate,'') 'Panel Tax'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_billing b ignore index(paymenttype) on b.tenant_id=a.tenant_id and b.pid=a.pid and b.encounter=a.encounter
        left join tbase_prescriptions c on c.tenant_id=a.tenant_id and c.id=b.orderid and b.code_type='PRESCRIPTIONS'
        left join tbase_users c2 on c2.tenant_id=a.tenant_id and c2.id=c.user
        left join tbase_users d on d.tenant_id=a.tenant_id and d.id=b.provider_id
        where a.encounterstatus=1 and b.activity=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'encounter_payments.xlsx'=>"select IFNULL(b.id,'') 'DataRow ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(a.pid,'') 'Patient ID', IFNULL(b.dtime,'') 'Payment Date', IFNULL(b.bill_no,'') 'Bill No', IFNULL(b.method,'') 'Payment Method'
        , IFNULL(b.amount1,0)+IFNULL(b.amount2,0) 'Payment Amount', IFNULL(cheque_date,'') 'Cheque Date', IFNULL(b.cheque_bankname,'') 'Cheque Bank Name', IFNULL(b.cc_type,'') 'Credit Card type', IFNULL(b.is_copay,0) 'Is Copay'
        , IFNULL(b.cc_no,'') 'Credit Card No', IFNULL(b.cc_expirydate,'') 'Credit Card Expiry', IFNULL(b.remarks,'') 'Remarks', IFNULL(b.receipt_no,'') 'Receipt No', IFNULL(d.username,IFNULL(b.user,'')) 'Created By'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_payments b on b.tenant_id=a.tenant_id and b.pid=a.pid and b.encounter=a.encounter
        left join tbase_ar_session c on c.tenant_id=a.tenant_id and c.session_id=b.session_id
        left join tbase_users d on d.tenant_id=a.tenant_id and d.id=c.user_id
        where a.encounterstatus=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'encounter_mc.xlsx'=>"select IFNULL(b.id,'') 'DataRow ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(a.pid,'') 'Patient ID', IFNULL(b.from_date,'') 'MC From Date', IFNULL(b.to_date,'') 'MC To Date'
        , IFNULL(b.from_time,'') 'Timeslip From Time', IFNULL(b.to_time,'') 'Timeslip To Time', IFNULL(b.description,'') 'Description', IFNULL(d.username,'') 'Created By'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_mc_timeslip b on b.tenant_id=a.tenant_id and b.pid=a.pid and b.encounter=a.encounter
        left join tbase_users d on d.tenant_id=a.tenant_id and d.id=b.added_by
        where a.encounterstatus=1 and b.status=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'encounter_ref_letter.xlsx'=>"select IFNULL(b.id,'') 'DataRow ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(d.username,IFNULL(b.user,'')) 'Refer By', IFNULL(d2.username,IFNULL(b.refer_to_other,'')) 'Refer To'
        , IFNULL(b.refer_date,'') 'Refer Date', IFNULL(b.body,'') 'Refer Msg', IFNULL(b.refer_diag,'') 'Refer Diag', IFNULL(b.refer_risk_level,'') 'Refer Level'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_transactions b on b.tenant_id=a.tenant_id and b.pid=a.pid and b.encounter=a.encounter
        left join tbase_users d on d.tenant_id=a.tenant_id and d.id=b.refer_from
        left join tbase_users d2 on d2.tenant_id=a.tenant_id and d2.id=b.refer_to
        where a.encounterstatus=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'encounter_attachment.xlsx'=>"select IFNULL(b.id,'') 'DataRow ID', IFNULL(a.encounter,'') 'Encounter ID', IFNULL(b.file_name,'') 'Tmp Name', IFNULL(b.file_original_name,'') 'Original Name'
        , IFNULL(b.file_size,'') 'File Size (Bytes)', IFNULL(b.file_type,'') 'File MIME Type', IFNULL(b.uploaded_at,'') 'Uploaded At', IF(IFNULL(b.is_mobile_uploaded,0)=0, 'NO', 'YES') 'Is Mobile'
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_encounter_documents b on b.tenant_id=a.tenant_id and b.pid=a.pid and b.encounter_id=a.encounter
        where a.encounterstatus=1 and b.status=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'",
    'daily_sales_report.xlsx'=>"select a.*,ifnull(b.cash_payments,0) cash_payments,ifnull(b.non_cash_payments,0) non_cash_payments,ifnull(b.writeoff_payments,0) writeoff_payments,ifnull(b.total_payments,0) total_payments from (
        select a.date enc_date,a.encounter,d.pubpid,trim(concat(d.fname,' ',d.lname)) patient_name,ifnull(c.name,'') insurance_name
        ,sum(ifnull(selfpay_amount,0)+ifnull(selfpay_taxrate,0)) selfpay_amount
        ,sum(ifnull(inspay_amount,0)+ifnull(inspay_taxrate,0)) inspay_amount
        ,sum(ifnull(selfpay_amount,0)+ifnull(selfpay_taxrate,0)+ifnull(inspay_amount,0)+ifnull(inspay_taxrate,0)) total_bill_amount
        from tbase_form_encounter a ignore index(paymenttype)
        join tbase_billing bill ignore index(paymenttype) on bill.activity=1 and bill.tenant_id=a.tenant_id and bill.pid=a.pid and bill.encounter=a.encounter
        left join tbase_insurance_data b on b.tenant_id=a.tenant_id and b.id=a.insurance_id
        left join tbase_insurance_companies c on c.tenant_id=a.tenant_id and c.id=b.provider
        left join tbase_patient_data d on d.tenant_id=a.tenant_id and d.pid=a.pid
        where a.encounterstatus=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'
        group by a.encounter) a
        left join
        (select a.facility_id,a.encounter
        ,sum(if(ifnull(c.method,'')='cash',c.amount1+c.amount2,0)) cash_payments
        ,sum(if(ifnull(c.method,'')<>'write_off' and ifnull(c.method,'')<>'cash',c.amount1+c.amount2,0)) non_cash_payments
        ,sum(if(ifnull(c.method,'')='write_off',c.amount1+c.amount2,0)) writeoff_payments
        ,sum(c.amount1+c.amount2) total_payments
        from tbase_form_encounter a ignore index(paymenttype)
        left join tbase_payments c on c.tenant_id=a.tenant_id and c.pid=a.pid and c.encounter=a.encounter
        where a.encounterstatus=1 and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'
        group by a.encounter) b on a.encounter=b.encounter
        order by a.enc_date",
    'posted_invoices.xlsx'=>"select b.id 'DataRow ID', b.encounter, b.billed_on_posted, b.paid_on_posted, b.created_at, b.updated_at
        , a.invoice_no, a.printed_inv_no, a.printed_inv_date, a.company_id, ifnull(d.name,'') AS 'Facility', a.start_date, a.end_date
        , IFNULL(c.username,'System') 'Created By'
        from tbase_invoice_header a
        join tbase_invoice_items b on b.tenant_id=a.tenant_id and b.invoice_header_id=a.id
        left join users_secure c on c.id=a.created_by
        left join tbase_facility d on d.tenant_id=a.tenant_id and d.id=b.facility_id
        where a.deleted_at='0000-00-00 00:00:00' and b.deleted_at='0000-00-00 00:00:00'
        and a.facility_id='{facility_id}' and a.tenant_id='{tenant_id}'
        order by a.invoice_no",
);

require_once dirname(__FILE__).'/../multitenant/Classes/drugMainClass.php';
require_once dirname(__FILE__).'/../multitenant/Classes/s3Class.php';

class data_export_func extends drugMainClass{

    var $email_list = array('jacky.low@bm-sms.my');
    var $start_date = "";
    var $end_date = "";
    var $facility_id = "";
    var $facility_name = "";
    var $tenant_id = "";
    var $alldata_flag = false;
    var $attachment_flag = false;
    var $email_report_flag = false;
    var $no_compress_flag = false;
    var $to_s3_flag = false;
    var $s3_path = "prod-cis-downloadable";
    var $s3_url_expire = 31536000;
    var $report_list = array();
    var $data_count_list = array();
    var $zip_attachment = false;
    var $zip_report = false;
    var $report_include = false;
    var $report_exclude = false;
    var $excel_max_rows = 50000;
    var $url_only_path = false;
    var $put_to_s3 = false;
    var $s3Class = false;
    var $tmp_path = false;
    var $multi_thread = false;
    var $query_limit = false;
    var $data_count_flag = false;
    var $filename_prefix = "";
    var $filename_fixed = "";

    public function __construct() {
        parent::__construct();

        $this->s3Class = new s3Class;

        $opt = getopt('', array('facility::','alldata::','attachment::','attachment-only::','exclude::','include::','path::','email::','to-s3::','signed-url-expire::','url-only::','no-compress::','tmp-path::','put-to-s3-only::','multi-thread::','memory-limit::','query-limit::','filename-prefix::','max-row::','filename-fixed::','data-count::'));
        if(!empty($opt['facility']) && ($result = $this->_db_query('SELECT id,name,tenant_id FROM tbase_facility WHERE id=? LIMIT 1', array($opt['facility'])))){
            if($this->set_tenant($result[0]['tenant_id'])){
                $this->facility_id = $result[0]['id'];
                $this->facility_name = $result[0]['name'];
                $this->tenant_id = $result[0]['tenant_id'];
            }
        }
        if(isset($opt['memory-limit']) && strlen($opt['memory-limit'])>0){
            ini_set('memory_limit', $opt['memory-limit']);
        }else{
            ini_set('memory_limit', '2048M');
        }
        if(isset($opt['alldata'])){
            $this->alldata_flag = true;
        }
        if(isset($opt['attachment'])){
            $this->attachment_flag = true;
            if(strlen($opt['attachment'])>0){
                $this->attachment_flag = strtolower($opt['attachment']);
            }
        }
        if(isset($opt['no-compress'])){
            $this->no_compress_flag = true;
        }
        if(isset($opt['data-count'])){
            $this->data_count_flag = true;
        }
        if(isset($opt['query-limit']) && strlen($opt['query-limit'])>0){
            $this->query_limit = $opt['query-limit'];
        }
        if(isset($opt['filename-prefix']) && strlen($opt['filename-prefix'])>0){
            $this->filename_prefix = $opt['filename-prefix'];
        }
        if(isset($opt['filename-fixed']) && strlen($opt['filename-fixed'])>0){
            $this->filename_fixed = $opt['filename-fixed'];
        }
        if(isset($opt['max-row']) && strlen($opt['max-row'])>0){
            $this->excel_max_rows = $opt['max-row'];
        }
        
        if(isset($opt['to-s3'])){
            $this->to_s3_flag = true;
            if(strlen($opt['to-s3'])>0){
                $this->s3_path = $opt['to-s3'];
            }
        }
        $this->s3Class->selected_bucket = $this->s3_path;

        $this->tmp_path = rtrim(getcwd(),DIRECTORY_SEPARATOR);
        foreach(array("path","tmp-path") as $v){
            if(isset($opt[$v])){
                $this->tmp_path = rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR);
                if(strlen($opt[$v])>0){
                    if(file_exists($opt[$v])){
                        $this->tmp_path = rtrim($opt[$v],DIRECTORY_SEPARATOR);
                    }else{
                        echo date("Y-m-d H:i:s")." Warning: Temporary path no exists! => ".$opt[$v]."\n";
                    }
                }
                break;
            }
        }

        if(isset($opt['put-to-s3-only']) && strlen($opt['put-to-s3-only'])>0){
            $this->put_to_s3 = $opt['put-to-s3-only'];
        }

        if(isset($opt['url-only']) && strlen($opt['url-only'])>0){
            $this->to_s3_flag = true;
            $this->url_only_path = $opt['url-only'];
        }
        if(isset($opt['signed-url-expire']) && strlen($opt['signed-url-expire'])>0){
            $this->s3_url_expire = $opt['signed-url-expire'];
        }
        
        if(isset($opt['exclude']) && strlen($opt['exclude'])>0){
            $this->report_exclude = array_map("trim",explode(",",$opt['exclude']));
        }
        if(isset($opt['include'])){
            $this->alldata_flag = true;
            if(strlen($opt['include'])>0){
                $this->report_include = array_map("trim",explode(",",$opt['include']));
            }
        }

        if(isset($opt['attachment-only'])){
            if(!$this->attachment_flag){
                $this->attachment_flag = true;
            }
            $this->report_include = array();
        }

        if(isset($opt['email'])){
            $this->email_report_flag = true;
            if(strlen($opt['email'])>0){
                $this->email_list = array_map("trim",explode(",",$opt['email']));
            }
        }

        /*multi thread option*/
        if(isset($opt['multi-thread'])){
            $this->multi_thread = 4;
            if(strlen($opt['multi-thread'])>0 && intval($opt['multi-thread'])>0){
                $this->multi_thread = intval($opt['multi-thread']);
            }
            $this->alldata_flag = true;
            $temp = $this->tmp_path."/thread";
            exec("rm -fr ".$temp."; mkdir ".$temp."; chmod 0777 ".$temp);
            $this->tmp_path = $temp;
        }
    }

    public function get_url($location = "", $timespan = 0){
        if($timespan==0){
            $timespan = $this->s3_url_expire;
        }
        if(!is_numeric($timespan)){
            if(($temp = strtotime($timespan)) && ($temp - time())>10){
                $timespan = $temp - time();
            }else{
                $timespan = 31536000;
            }
        }
        if( $location!="" && ($temp = $this->s3Class->bucket_get_url($location,$timespan)) !== FALSE ){
            return $temp;
        }
        return false;
    }

    public function put_to_s3($file = "", $location = ""){
        if(file_exists($file)){
            if($location==""){
                $location = basename($file);
            }
            if($this->s3Class->bucket_put_file($file, $location)){
                return $location;
            }
        }
        return false;
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
	/*
        if($this->email_report_flag){
            if($this->zip_report){
                $mail->AddAttachment($this->zip_report,'report.zip');
            }
            if($this->zip_attachment){
                $mail->AddAttachment($this->zip_attachment,'attachment.zip');
            }
        }
	*/
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $msg_body;
        $mail->send();
    }

    public function get_task_list(){
        global $data_list,$facility_list;
        $temp = $facility_list;
        if($this->alldata_flag){
            $temp += $data_list;
        }
        if(is_array($this->report_include)){
            $temp2 = array();
            foreach($temp as $k => $v){
                foreach($this->report_include as $k2){
                    if(strtolower(trim($k))==strtolower(trim($k2))){
                        $temp2[$k] = $v;
                        continue(2);
                    }
                }
            }
            $temp = $temp2;
        }
        if(is_array($this->report_exclude)){
            $temp2 = array();
            foreach($temp as $k => $v){
                foreach($this->report_exclude as $k2){
                    if(strtolower(trim($k))==strtolower(trim($k2))){
                        continue(2);
                    }
                }
                $temp2[$k] = $v;
            }
            $temp = $temp2;
        }

        return $temp;
    }
    
    public function start_task(){
        $this->start_timestamp = $ctime = time();
        $msg = "Export Data Done - ".$this->tenant_id."_".$this->facility_name."_".$this->facility_id."_".$ctime;
        $contents = "";
        echo date("Y-m-d H:i:s")." Start...\n";
        if($this->put_to_s3){
            echo date("Y-m-d H:i:s")." Task: Put file ".$this->put_to_s3." to S3...\n";
            if(($result = $this->put_to_s3($this->put_to_s3))){
                $this->url_only_path = $result;
            }else{
                echo date("Y-m-d H:i:s")." Fail to put S3!\n";
            }
        }
        if($this->multi_thread && $this->facility_id!=""){
            $task_list = array();
            if($this->attachment_flag){
                $task_list = array("encounter_documents"=>"encounter_documents") + $this->get_task_list();
            }else{
                $task_list = $this->get_task_list();
            }
            $task_list2 = array();
            $count = 0;
            $count2 = 0;
            $total_report = sizeof($task_list);
            $total_report2 = 0;
            $selected_task = "";
            echo date("Y-m-d H:i:s")." Total Reports: ".$total_report."\n";
            while(true){
                $temp = exec('ps -ef | grep -v "grep" | grep -Pi "[^\.]php.*data_export_func.php.*--facility.*--no-compress.*--include" -c',$tmp1,$tmp2);
                if(!is_numeric($temp) || $temp==""){
                    echo date("Y-m-d H:i:s")." Unable to use multi thread function!\n";
                    exit;
                }
                if($this->multi_thread > $temp){
                    if(sizeof($task_list2)==0){
                        foreach($task_list as $task => $v){
                            $selected_task = $task;
                            $count2 = 0;
                            $total_report2 = 0;
                            $count++;
                            $code = "";
                            if($task=="encounter_documents"){
                                $code = '/usr/bin/php /var/www/html/prod/app/multitenant_script/data_export_func.php --facility="'.$this->facility_id.'" --tmp-path="'.$this->tmp_path.'" --attachment-only ';
                            }else{
                                $sql = "SELECT COUNT(*) AS counting FROM (".$this->query_text_replace($v).") a";
                                if(($r0 = sqlQuery($sql)) && ($i = $r0['counting']) && $i > $this->excel_max_rows){
                                    $j = 0;
                                    while($j<$i){
                                        $task_list2[] = $j.",".$this->excel_max_rows;
                                        $j = $j + $this->excel_max_rows;
                                    }
                                    $total_report2 = sizeof($task_list2);
                                }else{
                                    $code = '/usr/bin/php /var/www/html/prod/app/multitenant_script/data_export_func.php --facility="'.$this->facility_id.'" --tmp-path="'.$this->tmp_path.'" --no-compress --include="'.$task.'" ';
                                }
                            }
                            echo date("Y-m-d H:i:s")." Task: Process report (".$count."/".$total_report.") => ".$code."\n";
                            if($code!=""){
                                exec($code.' > /dev/null 2>&1 & ');
                            }
                            array_shift($task_list);
                            break;
                        }
                    }
                    foreach($task_list2 as $task2 => $v2){
                        $count2++;
                        $nname = explode(".",$selected_task);
                        $next = array_pop($nname);
                        $nname = implode(".", $nname);
                        $prefix = $nname."_F".sprintf("%02d",$count2).".".$next;
                        $code = '/usr/bin/php /var/www/html/prod/app/multitenant_script/data_export_func.php --facility="'.$this->facility_id.'" --tmp-path="'.$this->tmp_path.'" --no-compress --include="'.$selected_task.'" --query-limit="'.$v2.'" --filename-fixed="'.$prefix.'" ';
                        echo date("Y-m-d H:i:s")." Task: Process sub report (".$count2."/".$total_report2.") => ".$code."\n";
                        exec($code.' > /dev/null 2>&1 & ');
                        array_shift($task_list2);
                        break;
                    }
                }
                if(sizeof($task_list)==0 && sizeof($task_list2)==0 && $temp==0){
                    break;
                }
                sleep(1);
            }

            if(!$this->no_compress_flag){
                $zip_name = str_replace(" ","_",dirname($this->tmp_path)."/Export_Data_".$this->tenant_id."_".$this->facility_name."_".$ctime.".zip");
                echo date("Y-m-d H:i:s")." Task: zip processing => ".$zip_name."\n";
                sleep(5);
                exec("zip -9 -j -r \"".$zip_name."\" ".$this->tmp_path."/*");
                if(file_exists($zip_name)){
                    echo date("Y-m-d H:i:s")." Task: zip created successfully => ".$zip_name."\n";
                    $this->zip_report = $zip_name;
                }
            }
        }else if($this->url_only_path){
            if(($url = $this->get_url($this->url_only_path))){
                $msg = "S3 signed URL - ".$this->url_only_path." (".$ctime.")";
                $contents = "S3 services was used to stored the exported files and below is the signed url list:<br><br><br>".$url;
                echo date("Y-m-d H:i:s")." Task: ".$msg."\n"."Signed URL: ".$url."\n";
            }else{
                echo date("Y-m-d H:i:s")." Fail to generate S3 signed URL!\n";
            }
        }else if($this->facility_id!=""){
            $msg = "Export Data Done - ".$this->tenant_id."_".$this->facility_name."_".$this->facility_id."_".$ctime;
            $contents = "The exported files was stored in the server temporary folder. Please login server to download the files.";
            $this->report_process();
            if(!$this->no_compress_flag){
                if($this->zip_report(true)){
                    $tname = str_replace(" ","_",$this->tmp_path.str_replace(" ","_","/Export_Data_".$this->tenant_id."_".$this->facility_name."_".$ctime.".zip"));
                    rename($this->zip_report, $tname);
                    $this->zip_report = $tname;
                }
            }
            if($this->attachment_flag){
                echo date("Y-m-d H:i:s")." Task: Downloading attachment from S3...\n";
                if($this->attachment_flag==="php" && $this->get_attachment()){
                    $tname = str_replace(" ","_",$this->tmp_path.str_replace(" ","_","/Export_Attachment_".$this->tenant_id."_".$this->facility_name."_".$ctime.".zip"));
                    rename($this->zip_attachment, $tname);
                    $this->zip_attachment = $tname;
                }else{
                    $this->get_attachment2();
                }
            }
            echo date("Y-m-d H:i:s")." Task: ".$msg."\n";
        }else{
            echo date("Y-m-d H:i:s")." Invalid facility_id!\n";
        }

        /*s3 storage*/
        if(!$this->no_compress_flag && $this->to_s3_flag){
            $url_list = array();
            foreach(array($this->zip_report,$this->zip_attachment) as $v){
                $location = basename($v);
                if($this->put_to_s3($v, $location)){
                    $url_list[] = $this->get_url($location);
                }
            }
            if(sizeof($url_list)>0){
                $contents = "S3 services was used to stored the exported files and below is the signed url list:<br><br><br>".implode("<br>", $url_list);
                echo date("Y-m-d H:i:s")." Signed URL:\n".implode("\n",$url_list)."\n";
            }
        }

        if($this->email_report_flag){
            $this->sendmail($this->email_list,$contents,$msg);
        }

        echo date("Y-m-d H:i:s")." End...\n";
    }

    public function report_process(){
        foreach($this->report_list as $k => $v){
            if(file_exists($v)){
                unlink($v);
            }
        }
        $this->report_list = array();
        $this->data_count_list = array();
        $temp = $this->get_task_list();
        foreach($temp as $name => $sql){
            $header_list = array();
            if(strlen($sql)>0){
                $temp_sql = $sql." LIMIT 0 ";
                if(($db_obj = $this->_db_execute($temp_sql)) && is_object($db_obj)){
                    foreach(mysqli_fetch_fields($db_obj) as $k => $v){
                        $header_list[$k] = $v->name;
                    }
                    mysqli_free_result($db_obj);
                    $db_obj = null;
                }
            }
            $result = false;
            $sql = $this->query_text_replace($sql);
            if(strlen($sql)>0){
                sqlStatement("SET NAMES 'utf8'");
                $result = sqlStatement($sql);
            }
            $is_csv = ((strpos($name, "csv")!==FALSE)?true:false);
            if($is_csv){
                $filepath = tempnam($this->tmp_path, "rep");
                $file = fopen($filepath,"w");
                $count = 0;
                fputcsv($file, $header_list);
                if($result && sqlNumRows($result)){
                    while($data = sqlFetchArray($result)){
                        fputcsv($file,$data);
                        $count++;
                    }
                }
                fclose($file);
                $newpath = dirname($filepath)."/".(($this->filename_fixed!="")?$this->filename_fixed:$this->filename_prefix.$name);
                rename($filepath, $newpath);
                $this->report_list[$name] = $newpath;
                echo date("Y-m-d H:i:s")." Report generated: ".$name." => ".$newpath."\n";
                $this->data_count_list[$name] = $count;
            }else{
                $stop_excel = 0;
                $file_size = 1;
                $count = 0;
                while($stop_excel==0){
                    $filepath = tempnam($this->tmp_path, "rep");
                    $objPHPExcel = new PHPExcel();
                    $objPHPExcel->setActiveSheetIndex(0);
                    $sheet = $objPHPExcel->getActiveSheet();
                    $row = 1;
                    if($row==1){
                        $col = 0;
                        foreach($header_list as $v2){
                            $sheet->setCellValueExplicitByColumnAndRow($col, $row, $v2);
                            $col++;
                        }
                        $row++;
                    }
                    if($result && sqlNumRows($result)){
                        while($data = sqlFetchArray($result)){
                            $col = 0;
                            $count++;
                            foreach($header_list as $v2){
                                $d = $data[$v2];
                                $sheet->setCellValueExplicitByColumnAndRow($col, $row, $d);
                                $col++;
                            }
                            $row++;
                            if(!$this->query_limit && (sqlNumRows($result) > $count) && ($row - 2) >= $this->excel_max_rows){
                                $nname = explode(".",$name);
                                $next = array_pop($nname);
                                $nname = implode(".",$nname);
                                $name = preg_replace("#_?F[0-9]+_?$#iu", "", $nname)."_F".sprintf("%02d",$file_size).".".$next;
                                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                                $objWriter->save($filepath);
                                $objPHPExcel->disconnectWorksheets();
                                $objWriter = null;
                                $objPHPExcel = null;
                                $newpath = dirname($filepath)."/".(($this->filename_fixed!="")?$this->filename_fixed:$this->filename_prefix.$name);
                                rename($filepath, $newpath);
                                $this->report_list[$name] = $newpath;
                                echo date("Y-m-d H:i:s")." Report generated: ".$name." => ".$newpath."\n";
                                $this->data_count_list[$name] = $row - 2;
                                $file_size++;
                                $nname = explode(".",$name);
                                $next = array_pop($nname);
                                $nname = implode(".",$nname);
                                $name = preg_replace("#_?F[0-9]+_?$#iu", "", $nname)."_F".sprintf("%02d",$file_size).".".$next;
                                continue(2);
                            }
                        }
                    }
                    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                    $objWriter->save($filepath);
                    $objPHPExcel->disconnectWorksheets();
                    $objWriter = null;
                    $objPHPExcel = null;
                    $stop_excel = 1;
                    $newpath = dirname($filepath)."/".(($this->filename_fixed!="")?$this->filename_fixed:$this->filename_prefix.$name);
                    rename($filepath, $newpath);
                    $this->report_list[$name] = $newpath;
                    echo date("Y-m-d H:i:s")." Report generated: ".$name." => ".$newpath."\n";
                    $this->data_count_list[$name] = $row - 2;
                }
            }
            flush();
        }

        /*data count summary file*/
        if(!$this->multi_thread && $this->data_count_flag && sizeof($this->data_count_list)>0){
            $name = "data_count.txt";
            $filepath = tempnam($this->tmp_path, "rep");
            $newpath = dirname($filepath)."/".$name;
            rename($filepath, $newpath);
            file_put_contents($newpath, json_encode($this->data_count_list));
            $this->report_list[$name] = $newpath;
            echo date("Y-m-d H:i:s")." Report generated: ".$name." => ".$newpath."\n";
        }
        
        return $this->report_list;
    }

    public function zip_report($remove_file = false){
        if(file_exists($this->zip_report)){
            unlink($this->zip_report);
        }
        $this->zip_report = false;
        if($this->report_list && sizeof($this->report_list)>0){
            $zip = new ZipArchive;
            $zipfile = tempnam($this->tmp_path, "zip");
            if ($zip->open($zipfile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) === TRUE) {
                foreach($this->report_list as $k => $v){
                    $zip->addFile($v, $k);
                }
                $zip->close();
                if($remove_file){
                    foreach($this->report_list as $k => $v){
                        if(file_exists($v)){
                            unlink($v);
                        }
                    }
                    $this->report_list = array();
                }
                $this->zip_report = $zipfile;
                return $zipfile;
            }
        }
        return false;
    }

    public function query_text_replace($sql = ""){
        foreach(array('facility_id','tenant_id') as $v){
            $temp = "{".$v."}";
            if(strpos($sql, $temp)!==FALSE && isset($this->{$v})){
                $sql = str_replace($temp, $this->{$v}, $sql);
            }
        }
        if($this->query_limit){
            $sql = "SELECT * FROM (".$sql.") a LIMIT ".$this->query_limit;
        }
        return $sql;
    }

    public function get_attachment(){
        require_once dirname(__FILE__).'/../multitenant/Classes/s3Class.php';
        $s3Class = new s3Class;
        $s3Class->selected_bucket = "stat.mimscis.com";
        if(file_exists($this->zip_attachment)){
            unlink($this->zip_attachment);
        }
        $this->zip_attachment = false;
        $location = 'patient_documents/'.$this->tenant_id;
        if(($temp = $s3Class->bucket_list($location))){
            $zip = new ZipArchive;
            $zipfile = tempnam($this->tmp_path, "zip");
            if ($zip->open($zipfile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) === TRUE) {
                foreach($temp as $file){
                    $zip->addFromString($file['name'], $s3Class->bucket_get($file['name']));
                }
                $zip->close();
                $this->zip_attachment = $zipfile;
                return $zipfile;
            }
        }
        return false;
    }

    public function get_attachment2(){
        require_once dirname(__FILE__).'/../multitenant/Classes/s3Class.php';
        $s3Class = new s3Class;
        $s3Class->selected_bucket = "stat.mimscis.com";
        if(file_exists($this->zip_attachment)){
            unlink($this->zip_attachment);
        }
        $this->zip_attachment = false;
        $location = 'patient_documents/'.$this->tenant_id;
        if(($temp = $s3Class->bucket_list($location))){
            $folder = $this->tmp_path."/attachment";
            exec("rm -fr ".$folder."; mkdir ".$folder."; chmod 0777 ".$folder);
            
            foreach($temp as $file){
                $path = $folder;
                foreach(explode("/", dirname($file['name'])) as $sub){
                    $path .= "/".$sub;
                    if(!file_exists($path)){
                        mkdir($path);
                        chmod($path,0777);
                    }
                }
                $path .= "/".basename($file['name']);
                file_put_contents($path,$s3Class->bucket_get($file['name']));
            }
            if(!$this->no_compress_flag){
                $zip_name = str_replace(" ","_",$this->tmp_path."/Export_Attachment_".$this->tenant_id."_".$this->facility_name."_".$this->start_timestamp.".zip");
                echo date("Y-m-d H:i:s")." Task: Attachment zip processing => ".$zip_name."\n";
                sleep(5);
                exec("cd \"".$folder."\"; zip -9 -r \"".$zip_name."\" *");
                if(file_exists($zip_name)){
                    echo date("Y-m-d H:i:s")." Task: Attachment zip created successfully => ".$zip_name."\n";
                    $this->zip_attachment = $zip_name;
                    return $zip_name;
                }
            }
        }
        return false;
    }

}

$class = new data_export_func();
$class->start_task();
