# Build the content as soon as the course row is read, before core's and the plugin's questions: a refused course
# still pays for its content and reads its contacts, though the answer is the same refusal.
s/(\n        \$course = \$DB->get_record\('course', \['id' => \$courseid\], dialogue::COURSE_FIELDS\);\n)/$1        if (\$course) {\n            dialogue::content(\$course);\n        }\n/;
