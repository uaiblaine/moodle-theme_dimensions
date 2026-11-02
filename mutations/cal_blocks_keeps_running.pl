# Drop an item still running when the window starts, which core's vault keeps.
s/ \|\| \$item\['timestart'\] \+ \$item\['timeduration'\] > \$from;/;/;
