# Never read the panel settings: the language string shows whatever the admin typed.
s/        \$value = trim\(\(string\) get_config\('theme_dimensions', \$setting\)\);\n/        \$value = '';\n/;
