# Stop writing the navbar's name into the user menu context: the navbar shows core's full name whatever was chosen.
s/\n[^\n]*\$context\['usermenu'\]\['dimensionsnavname'\] = identity::navbar\(\$USER\);//;
