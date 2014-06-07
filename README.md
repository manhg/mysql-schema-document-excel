Japanese-style database tables' description generator
=====================================================

This tool takes a database and generate table definition into an Excel file.
It supports auto column name translation descriptions.

Usage
=====

For simple usage, just fill database params in export.php and run the script.
Result will be stored in a folder "output".

You can customize how the result looked like by editing template.xlsx

For auto translations of field comment, the idea is:

* a field name "num_holiday" is broken down to "num" and "holiday"
* a list of translation is prepared in trans.txt
* trans.py will assist generate all tokens as "num", "holiday"
* fill by hand or using translator get all tokens "translated".
* field comment will be add when run "export.php"

Credit
======

This scripts get the job done thanks to:

PHPExcel (https://github.com/PHPOffice/PHPExcel)
Adminer (http://adminer.org)