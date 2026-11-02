# Ask the capability as core's view does, without doanything: an administrator with no role manages nothing.
s/has_capability\('moodle\/category:manage', \$category->get_context\(\)\)/has_capability('moodle\/category:manage', \$category->get_context(), null, false)/;
