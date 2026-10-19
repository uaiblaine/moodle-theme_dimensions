# Drop the layout clause: the login and maintenance pages are told about the actions too.
s/\n[^\n]*if \(in_array\(\(string\) \$page->pagelayout, self::EXCLUDED_LAYOUTS, true\)\) \{\n[^\n]*return false;\n[^\n]*\}//;
