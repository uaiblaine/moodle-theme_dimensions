# Keep the callback registered but never write: a new course gets no default.
s/landingdefaults::apply_to_new_course\(\(int\) \$data->id\);/return;/;
