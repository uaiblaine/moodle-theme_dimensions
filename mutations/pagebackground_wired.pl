# Stop printing Boost Union's page background image under 'Inherit', where nothing else prints it for this theme.
s/        \$scss \.= \\theme_dimensions\\local\\pagebackground::scss\(\);\n//;
