# Write the PUBLIC answer into core's course_image cache instead of core's own URL: the theme's
# route then leaks out of the public page into Boost Union's cards and the Dashboard, which are
# pages behind the login, where core's route is the one to use.
s/\$cached\[\$courseid\] = \$url\?->out_as_local_url\(\);/\$cached[\$courseid] = (\$public && isset(\$picked[\$courseid])) ? publicfiles::cover_url((object) ['id' => \$courseid], \$picked[\$courseid])->out_as_local_url() : \$url?->out_as_local_url();/;
