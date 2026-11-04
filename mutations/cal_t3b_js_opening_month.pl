# Open on today even in a month after the one asked for.
s/const openat = today\.startsWith\(win\.loaded\.months\[0\]\) \? todayat : -1;/const openat = todayat;/;
