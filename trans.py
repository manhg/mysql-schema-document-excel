#!/usr/bin/env python3
import re

'''
Genrate translate.ini using below SQL (replace xx with the database name)
SELECT distinct column_name, table_name FROM columns WHERE table_schema = 'xx';
'''

fields = open('translate.ini').read().split("\n")
items = set()
meaningful_regex = re.compile('([a-z]+)')
for f in fields:
    for i in meaningful_regex.findall(f):
        items.add(i)
print(items)