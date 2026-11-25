# Never paint the band on a render: after core's re-render the two counts stay empty.
s/\n(\s*)paint\(\);\n/\n/;
