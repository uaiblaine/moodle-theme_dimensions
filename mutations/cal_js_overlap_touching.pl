# Count touching ends as an overlap.
s/&& a\.timestart < b\.timestart \+ b\.timeduration && b\.timestart < a\.timestart \+ a\.timeduration;/\&\& a.timestart <= b.timestart + b.timeduration \&\& b.timestart <= a.timestart + a.timeduration;/;
