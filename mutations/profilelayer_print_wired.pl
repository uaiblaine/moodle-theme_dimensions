# Stop printing the stored layer: every page loses it while core's output after the main region stays.
s/return \$output \. \$this->render_from_template\('theme_dimensions\/profile_layer', \$layer\);/return \$output;/;
