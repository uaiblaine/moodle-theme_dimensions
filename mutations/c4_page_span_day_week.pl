# Ask for the open day without the week, so the month's grid judges it.
s/        \$day = self::day_from_request\(\$month, \$firstday, \$week\);\n/        \$day = self::day_from_request(\$month, \$firstday);\n/;
