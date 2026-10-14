# Hide the open poster when the criterion zeroes it: the panel is then drawn after a poster nobody can
# see, and the reader who opened the area loses it under a filter.
s/'hidden' => \$filtered && \$count === 0 && !\$isopen,/'hidden' => \$filtered && \$count === 0,/;
