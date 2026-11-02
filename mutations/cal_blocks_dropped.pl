# Keep the category events of a category local_unlistedcourses withholds: the blocks must not show them.
s/return !\$proxy \|\| isset\(\$listed\[\(int\) \$proxy->get\('id'\)\]\);/return true;/;
