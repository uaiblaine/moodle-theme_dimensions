# Send a course calendar without its category: the page opens on the whole calendar.
s/            \$category = \(int\) \$categoryof\(\$courseid\);/            \$category = 0;/;
