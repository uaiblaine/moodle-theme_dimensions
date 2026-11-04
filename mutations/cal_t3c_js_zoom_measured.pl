# Take the zoom of the last measure as unchanged once a new opening measured at the new zoom.
s/(        last\.current = track;\n)        zoomed\.current = zoom;\n/$1/;
