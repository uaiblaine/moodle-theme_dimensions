# Leave a whole-day item out of the strip, in the hour body.
s/\.filter\(\(entry\) => entry\.bar \|\| isAllDay\(entry\.item, zone\)\)/.filter((entry) => entry.bar)/;
