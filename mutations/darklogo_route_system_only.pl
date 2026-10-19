# Take the areas in any context: the route would hand a category's or a course's "logodark" to the setting route.
s/return \$context->contextlevel == CONTEXT_SYSTEM && in_array\(\$filearea, self::AREAS, true\);/return in_array(\$filearea, self::AREAS, true);/;
