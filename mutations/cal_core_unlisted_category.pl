# Keep the category events of a category local_unlistedcourses withholds.
s/return !\$proxy \|\| isset\(\$listed\[\(int\) \$proxy->get\('id'\)\]\);/return true;/;
