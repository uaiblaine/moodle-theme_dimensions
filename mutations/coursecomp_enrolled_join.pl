# Count users who hold the capability without asking whether they are enrolled: a role in the category is not a learner of the course.
s/get_enrolled_with_capabilities_join\(\$context, \x27\x27, self::GRADABLE\)/get_with_capability_join(\$context, self::GRADABLE, \x27u.id\x27)/;
