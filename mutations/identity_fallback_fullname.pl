# Drop the fallback: a person whose chosen field is empty, unknown or hidden from them is shown no name at all.
s/return self::text\(self::FULLNAME, \$user\);/return '';/;
