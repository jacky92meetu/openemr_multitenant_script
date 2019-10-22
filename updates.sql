
-- For the ability to change facility by doctor and locum by admin
ALTER TABLE `tbase_users` 
    ADD `is_locum` TINYINT NOT NULL DEFAULT '0' AFTER `newcrop_user_role`, 
    ADD `locum_expiry` DATE NOT NULL AFTER `is_locum`;

-- Update view

ALTER
 VIEW `users`
 AS SELECT
    `tbase_users`.`id` AS `id`,
    `tbase_users`.`username` AS `username`,
    `tbase_users`.`password` AS `password`,
    `tbase_users`.`authorized` AS `authorized`,
    `tbase_users`.`info` AS `info`,
    `tbase_users`.`source` AS `source`,
    `tbase_users`.`fname` AS `fname`,
    `tbase_users`.`mname` AS `mname`,
    `tbase_users`.`lname` AS `lname`,
    `tbase_users`.`federaltaxid` AS `federaltaxid`,
    `tbase_users`.`federaldrugid` AS `federaldrugid`,
    `tbase_users`.`upin` AS `upin`,
    `tbase_users`.`facility` AS `facility`,
    `tbase_users`.`facility_id` AS `facility_id`,
    `tbase_users`.`see_auth` AS `see_auth`,
    `tbase_users`.`active` AS `active`,
    `tbase_users`.`npi` AS `npi`,
    `tbase_users`.`title` AS `title`,
    `tbase_users`.`specialty` AS `specialty`,
    `tbase_users`.`billname` AS `billname`,
    `tbase_users`.`email` AS `email`,
    `tbase_users`.`email_direct` AS `email_direct`,
    `tbase_users`.`url` AS `url`,
    `tbase_users`.`assistant` AS `assistant`,
    `tbase_users`.`organization` AS `organization`,
    `tbase_users`.`valedictory` AS `valedictory`,
    `tbase_users`.`street` AS `street`,
    `tbase_users`.`streetb` AS `streetb`,
    `tbase_users`.`city` AS `city`,
    `tbase_users`.`state` AS `state`,
    `tbase_users`.`zip` AS `zip`,
    `tbase_users`.`street2` AS `street2`,
    `tbase_users`.`streetb2` AS `streetb2`,
    `tbase_users`.`city2` AS `city2`,
    `tbase_users`.`state2` AS `state2`,
    `tbase_users`.`zip2` AS `zip2`,
    `tbase_users`.`phone` AS `phone`,
    `tbase_users`.`fax` AS `fax`,
    `tbase_users`.`phonew1` AS `phonew1`,
    `tbase_users`.`phonew2` AS `phonew2`,
    `tbase_users`.`phonecell` AS `phonecell`,
    `tbase_users`.`notes` AS `notes`,
    `tbase_users`.`cal_ui` AS `cal_ui`,
    `tbase_users`.`taxonomy` AS `taxonomy`,
    `tbase_users`.`ssi_relayhealth` AS `ssi_relayhealth`,
    `tbase_users`.`calendar` AS `calendar`,
    `tbase_users`.`abook_type` AS `abook_type`,
    `tbase_users`.`pwd_expiration_date` AS `pwd_expiration_date`,
    `tbase_users`.`pwd_history1` AS `pwd_history1`,
    `tbase_users`.`pwd_history2` AS `pwd_history2`,
    `tbase_users`.`default_warehouse` AS `default_warehouse`,
    `tbase_users`.`irnpool` AS `irnpool`,
    `tbase_users`.`state_license_number` AS `state_license_number`,
    `tbase_users`.`switch_facility_authorization` AS `switch_facility_authorization`,
    `tbase_users`.`newcrop_user_role` AS `newcrop_user_role`,
    `tbase_users`.`is_locum` AS `is_locum`,
    `tbase_users`.`locum_expiry` AS `locum_expiry`,
    `tbase_users`.`tenant_id` AS `tenant_id`
FROM
    `tbase_users`
WHERE (`tbase_users`.`tenant_id` = substring_index(user(),'@',1));

-- Added new column to identify user while ending consultation
ALTER TABLE `tbase_form_encounter` ADD `end_consultation_by` INT(11) NULL DEFAULT NULL AFTER `end_consulation`;

-- Update view
ALTER
 VIEW `form_encounter`
 AS SELECT 
    `tbase_form_encounter`.`id` AS `id`,
    `tbase_form_encounter`.`date` AS `date`,
    `tbase_form_encounter`.`reason` AS `reason`,
    `tbase_form_encounter`.`facility` AS `facility`,
    `tbase_form_encounter`.`facility_id` AS `facility_id`,
    `tbase_form_encounter`.`pid` AS `pid`,
    `tbase_form_encounter`.`encounter` AS `encounter`,
    `tbase_form_encounter`.`onset_date` AS `onset_date`,
    `tbase_form_encounter`.`sensitivity` AS `sensitivity`,
    `tbase_form_encounter`.`prescription_note` AS `prescription_note`,
    `tbase_form_encounter`.`billing_note` AS `billing_note`,
    `tbase_form_encounter`.`pc_catid` AS `pc_catid`,
    `tbase_form_encounter`.`last_level_billed` AS `last_level_billed`,
    `tbase_form_encounter`.`last_level_closed` AS `last_level_closed`,
    `tbase_form_encounter`.`last_stmt_date` AS `last_stmt_date`,
    `tbase_form_encounter`.`stmt_count` AS `stmt_count`,
    `tbase_form_encounter`.`provider_id` AS `provider_id`,
    `tbase_form_encounter`.`supervisor_id` AS `supervisor_id`,
    `tbase_form_encounter`.`invoice_refno` AS `invoice_refno`,
    `tbase_form_encounter`.`referral_source` AS `referral_source`,
    `tbase_form_encounter`.`billing_facility` AS `billing_facility`,
    `tbase_form_encounter`.`encounterstatus` AS `encounterstatus`,
    `tbase_form_encounter`.`action` AS `action`,
    `tbase_form_encounter`.`paymenttype` AS `paymenttype`,
    `tbase_form_encounter`.`start_consulation` AS `start_consulation`,
    `tbase_form_encounter`.`end_consulation` AS `end_consulation`,
    `tbase_form_encounter`.`queue_no` AS `queue_no`,
    `tbase_form_encounter`.`end_consultation_by` AS `end_consultation_by`,
    `tbase_form_encounter`.`tenant_id` AS `tenant_id` 
FROM `tbase_form_encounter` 
WHERE (`tbase_form_encounter`.`tenant_id` = substring_index(user(),'@',1))
