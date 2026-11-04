# Leave a missing course id to core: its "invalid course" error would tell an unlisted course from no course.
s/(record_exists\('course', \['id' => \$courseid\]\)\) \{\n\s*return) true;/$1 false;/;
