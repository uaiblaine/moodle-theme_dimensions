# Keep a custom-field filter value no option has, so the listing empties with no chip to undo it.
s/\$value > 0 && \$value <= \(\$counts\[\(int\) \$fieldid\] \?\? 0\)/\$value > 0/;
