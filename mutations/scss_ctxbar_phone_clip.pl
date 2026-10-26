# Leave the nav unclipped while the layer is in: the global items show below the bar.
s/(\.navbar\.fixed-top\.dimensions-ctx-on \{\n\s*)overflow: hidden;\n/$1/;
