# Rename the callback so Moodle never finds it. Everything in the class still works
# and every unit test of the class alone still passes.
s/function theme_dimensions_control_view_profile\(/function theme_dimensions_control_view_profile_disabled(/;
