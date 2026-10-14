# Take the route off the name file_pluginfile() dispatches to: every URL it mints is then 404.
s/function theme_dimensions_pluginfile\(/function theme_dimensions_pluginfile_unwired(/;
