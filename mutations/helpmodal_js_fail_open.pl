# Leave the popover hidden when the dialogue fails: the help vanishes with nothing in its place.
s/(\} catch \(error\) \{[^}]*?)\n[ \t]*reveal\(tip\);/$1/s;
