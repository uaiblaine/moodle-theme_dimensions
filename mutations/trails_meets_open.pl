# A period left open by a step that never ends does not reach later months.
s/\(\$open \|\| \$last >= \$window->tstart\)/(\$last >= \$window->tstart)/;
