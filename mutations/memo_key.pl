# Drop the course from the request memo key, so the site profile's answer is reused for the
# in-course page and the distinction the class exists to draw is lost.
s/\$key = \(int\) \$USER->id \. ':' \. \(int\) \$user->id \. ':' \. \(int\) \(\$course->id \?\? 0\);/\$key = (int) \$USER->id . ':' . (int) \$user->id;/;
