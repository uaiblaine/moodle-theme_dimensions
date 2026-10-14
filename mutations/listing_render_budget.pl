# Read the page's course rows one statement per card: the render costs grow with the page size.
s/\$courses = cards::records\(\$ids\);/\$courses = []; foreach (\$ids as \$one) { \$courses += cards::records([\$one]); }/;
