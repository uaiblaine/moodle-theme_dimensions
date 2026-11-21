# Core's loop again: one groups_get_all_groups() per enrolled course.
s/\$groups = self::user_group_ids\(\(int\) \$user->id, array_keys\(\$courses\)\);/\$groups = []; foreach (\$courses as \$c) { \$groups = array_merge(\$groups, array_keys(groups_get_all_groups(\$c->id, \$user->id))); }/;
