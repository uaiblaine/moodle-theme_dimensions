# A lapsed account that can view the course without being enrolled is no longer staff.
s{return is_viewing\(\$context, \$USER\)\n\s+\|\| }{return };
