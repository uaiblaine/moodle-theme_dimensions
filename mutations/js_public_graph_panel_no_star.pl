# Ask the bridge for the star from the posters' component, which the visitor's page mounts: the walk
# must follow a component's quoted AMD name through the bridge and find it.
s/templates = templates \|\| load\('core\/templates'\);/templates = templates || load('theme_dimensions\/categorystar');/;
