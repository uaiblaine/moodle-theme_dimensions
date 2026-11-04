# Move the position by the first prepended month alone: a batch of three leaves the view two months off.
s/shift = added\.reduce\(\(days, month\) => days \+ monthDays\(month\)\.length, 0\);/shift = monthDays(added[0]).length;/;
