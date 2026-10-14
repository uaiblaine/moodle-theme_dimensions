# Drop the viewer from the request memo key, so one person's answer is handed to the next
# one whenever \core\session\manager::set_user() switches $USER mid-request.
s/\$key = \(int\) \$USER->id \. ':' \. \(int\) \$user->id \. ':' \. \(int\) \(\$course->id \?\? 0\);/\$key = (int) \$user->id . ':' . (int) (\$course->id ?? 0);/;
