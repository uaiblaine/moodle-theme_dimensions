# Drop the start-date range from the statement the PHP-cut mode materialises its population with.
# The other mode keeps it, which is the whole shape of the defect: one page-cutting mode honours a
# date the reader picked and the other ignores it, from the same address, with only a setting
# between them and nothing on the page able to say which one answered. Anchored on what PRECEDES
# the date block (the search block, which only records() is followed by), because the code after
# the date block moves and a pattern reading forwards goes dead when it does.
s/(\$params \+= \$searchparams;\n[ \t]*\}\n[ \t]*\[\$datesql, \$dateparams\] = self::date_sql\(\$criteria\);\n[ \t]*if \(\$datesql !== ''\) \{\n)[ \t]*\$where \.= " AND \{\$datesql\}";\n/$1/;
