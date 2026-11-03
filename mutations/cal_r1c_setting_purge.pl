# A colour saved keeps the old stylesheet until somebody purges the caches.
s/(keycolour_setting\(\$name, \$title, \$description, \$default\);\n)            \$setting->set_updatedcallback\('theme_reset_all_caches'\);\n/$1/;
