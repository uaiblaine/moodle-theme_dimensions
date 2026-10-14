# Stop forcing the side-by-side arrangement: with Boost Union set to legacy, the aside
# the panel lives in is never drawn.
s/        \$context\['usesidebysideloginarrangement'\] = true;\n//;
