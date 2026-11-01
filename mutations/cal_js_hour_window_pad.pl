# Drop the hour of padding either side of the day's busy hours.
s/let from = Math\.max\(0, Math\.floor\(earliest \/ 60\) - 1\);/let from = Math.max(0, Math.floor(earliest \/ 60));/;
s/let to = Math\.min\(24, Math\.ceil\(latest \/ 60\) \+ 1\);/let to = Math.min(24, Math.ceil(latest \/ 60));/;
