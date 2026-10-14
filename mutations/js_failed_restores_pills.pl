# Hear the failure but leave the pills as the pending state left them: the grids stop being busy while
# every pill still says "...", and every poster is read as "..." by its description. The sweep
# exercises categorypanel_source_test's assertion.
s/\n    areas\.querySelectorAll\(SELECTORS\.area\)\.forEach\(\(poster\) => paintPill\(poster, isFiltered\(areas\)\)\);//;
