# Go compact at any width: above xl the hamburger appears beside a navigation with room to spare.
s/setCompact\(inrange && count > bar\.maxhidden\);/setCompact(count > bar.maxhidden);/;
