# The audience section of a public page would keep its trusted HTML.
s/\$values->audience = self::textarea_value\(\$data, \$context, \$filters, \$clean\);/\$values->audience = self::textarea_value(\$data, \$context, \$filters);/;
