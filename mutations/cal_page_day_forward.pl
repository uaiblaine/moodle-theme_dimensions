# Forward the address to the route without its open day.
s/        if \(self::is_day\(\$day\)\) \{\n            \$params\[self::PARAM_DAY\] = \$day;/        if (false) {\n            \$params[self::PARAM_DAY] = \$day;/;
