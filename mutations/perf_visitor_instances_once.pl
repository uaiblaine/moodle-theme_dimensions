# Give the login way in its own read of the instances again: the visitor's landing page pays a second statement.
s/self::login_way_in\(\$instances, \$plugins\)/self::login_way_in(enrol_get_instances(\$course->id, true), \$plugins)/;
