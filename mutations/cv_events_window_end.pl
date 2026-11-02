# Keep an event that starts after the window.
s/AND e\.timestart <= :windowend/AND e.timestart <= :windowend + 864000/;
