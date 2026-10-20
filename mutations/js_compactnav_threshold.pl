# Go compact at MAXHIDDEN items in "More", one early.
s/setCompact\(inrange && count > bar\.maxhidden\);/setCompact(inrange && count >= bar.maxhidden);/;
