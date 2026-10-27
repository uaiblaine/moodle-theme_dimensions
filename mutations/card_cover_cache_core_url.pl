# Write the PUBLIC answer into core's course_image cache instead of core's own URL: the theme's
# route then leaks out of the public page into Boost Union's cards and the Dashboard, which are
# pages behind the login, where core's route is the one to use.
s/self::warm_course_image_cache\(\$images\);/self::warm_course_image_cache(\$public && \$picked ? array_combine(array_keys(\$picked), array_map(fn(\$courseid, \$file) => publicfiles::cover_url((object) ['id' => \$courseid], \$file), array_keys(\$picked), \$picked)) + \$images : \$images);/;
