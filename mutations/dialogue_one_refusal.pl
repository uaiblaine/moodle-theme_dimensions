# Refuse a course the plugin withholds with the category's message: the service then tells it apart from every other miss.
s/(if \(!access::filter_courses\(\[\$courseid => \$course\]\)\) \{\n            throw new \\moodle_exception\()guard::ERROR_COURSE/$1guard::ERROR_CATEGORY/;
