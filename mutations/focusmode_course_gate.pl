# Make every course answer yes. The per course switch stops meaning anything and every
# activity on the site opens focused, which only a test that renders a course which did NOT
# switch it on can see.
s/!self::enabled_for_course\(\$courseid\)/!true/;
