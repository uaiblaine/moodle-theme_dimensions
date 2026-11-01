# Skip the showcase's decision layer: a course in an unlisted category reaches the calendar.
s/\$admitted = population::admitted\(\$root, \$page\);/\$admitted = \$page;/;
