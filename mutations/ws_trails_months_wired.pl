# The service answers one month whatever count it is asked for.
s/trails::month_window\(\$params\['month'\], \$params\['months'\]\),/trails::month_window(\$params['month']),/;
