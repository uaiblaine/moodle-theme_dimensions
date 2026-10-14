# Keep the root out of result mode under its search: its posters wear no pill, a poster with no match
# stays in the grid and the summary names nothing.
s/\$filtered = \$results\['filtered'\];/\$filtered = !\$isroot && \$results['filtered'];/;
