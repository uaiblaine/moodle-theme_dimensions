# Go compact at MAXHIDDEN items in "More", one early.
s/const overflows = inrange && count > bar\.maxhidden;/const overflows = inrange && count >= bar.maxhidden;/;
