# Keep a key of the logged-in page among the visitor's.
s/        return array_values\(array_intersect\(self::KEYS, \$names\)\);/        return array_values(\$names);/;
