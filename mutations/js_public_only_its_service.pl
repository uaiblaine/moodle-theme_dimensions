# Point the visitor's live listing at the logged-in listing's service. Every request then asks a
# function a visitor has no session for, and the module carries a logged-in service's name to the
# internet. Swapped rather than deleted, so the build still changes.
s/^const METHOD = 'theme_dimensions_get_public_category_rows';$/const METHOD = 'theme_dimensions_get_category_rows';/m;
