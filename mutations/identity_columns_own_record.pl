# Show a column of any record handed in: a caller passing another person's record prints their username, email or ID number.
s/\$value = self::own\(\$user\) \? (trim\(\(string\) \(\$user->\{\$field\} \?\? ''\)\)) : '';/\$value = $1;/;
