# Import the AMD panel into the logged-in module again: two writers of the same posters.
s/^(import Ajax from 'core\/ajax';\n)/$1import * as AreaPanel from 'theme_dimensions\/categoryareapanel';\n/m;
