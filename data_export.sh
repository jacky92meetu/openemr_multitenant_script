#!/bin/bash

while getopts f:e:c: option
do
case "${option}"
in
f) fid=${OPTARG};;
e) email=${OPTARG};;
c) cpu=${OPTARG};;
esac
done

if [ "${fid}" == "" ]; then
echo "Invalid Facility ID";
exit;
fi

if [ "${cpu}" == "" ]; then
cpu=4;
fi

#/usr/bin/nohup /usr/bin/php /var/www/html/prod/app/multitenant_script/data_export_func.php --facility="${fid}" --alldata --attachment --to-s3 --email
/usr/bin/nohup /usr/bin/php /var/www/html/prod/app/multitenant_script/data_export_func.php --facility="${fid}" --alldata --attachment --multi-thread="${cpu}" --to-s3 --email
