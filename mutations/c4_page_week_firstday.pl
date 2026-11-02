# Begin every week on Sunday, whatever the viewer's first weekday.
s/\$lead = \(\(int\) \$date->format\('w'\) - \$firstday \+ 7\) % 7;/\$lead = (int) \$date->format('w');/;
