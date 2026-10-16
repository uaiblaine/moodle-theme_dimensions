# Keep the filter line on one row however narrow: without JavaScript its controls run off the screen.
s/(\.dim-line \{\n        position: relative;\n        display: flex;\n        )flex-wrap: wrap;/${1}flex-wrap: nowrap;/;
