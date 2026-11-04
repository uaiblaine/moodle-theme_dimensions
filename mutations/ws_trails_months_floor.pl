# No month, or a negative count, reaches login and the window.
s/\|\| \$params\['months'\] < 1 \|\| \$params\['months'\]/|| \$params['months']/;
