# Judge the open day against the month's grid in the week view too.
s/\[\$from, \$to\] = \$week !== '' \? self::week\(\$week\) : self::grid\(\$month, \$firstday\);/[\$from, \$to] = self::grid(\$month, \$firstday);/;
