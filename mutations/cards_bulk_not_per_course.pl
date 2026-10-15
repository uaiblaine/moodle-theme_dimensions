# Read the custom fields one course at a time: the statement count grows with the page size.
s/return landingfields::values_many\(array_map\('intval', array_keys\(\$rows\)\), false\);/\$out = []; foreach (array_keys(\$rows) as \$courseid) { \$out[(int) \$courseid] = landingfields::values_many([(int) \$courseid], false)[(int) \$courseid]; } return \$out;/;
