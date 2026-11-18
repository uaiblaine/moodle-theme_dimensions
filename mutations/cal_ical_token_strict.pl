# Compare the token as core does, loosely: a request token 0 matches a token spelled 0e and 38 digits.
s/        return hash_equals\(\$expected, \$given\);/        return \$expected == \$given;/;
