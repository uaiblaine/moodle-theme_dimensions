# Drop the way into focus mode from the context layer.
s/\$layer\['hasfocus'\] = \$focusentry !== null;/\$layer['hasfocus'] = false;/;
