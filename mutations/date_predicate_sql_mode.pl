# Drop the start-date range from the predicate the SQL-cut mode cuts its page with, which is also
# the predicate its count, its grouped tally and its facet groups are taken over. The PHP-cut mode
# keeps it: two modes, one address, two answers. Anchored on the field-filter block above it, which
# only sql_predicate() has - see the note in the PHP-mode script for why it reads backwards.
s/(\$params \+= \$fieldparams;\n[ \t]*\}\n[ \t]*\[\$datesql, \$dateparams\] = self::date_sql\(\$criteria\);\n[ \t]*if \(\$datesql !== ''\) \{\n)[ \t]*\$where \.= " AND \{\$datesql\}";\n/$1/;
