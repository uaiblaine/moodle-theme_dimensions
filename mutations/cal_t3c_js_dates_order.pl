# Say dates out of order as a range backwards.
s/const last = to < from \? from : to;/const last = to;/;
