# Drop an event that spans into the window.
s/AND \(e\.timestart >= :windowstart OR e\.timestart \+ e\.timeduration > :spanstart\)/AND (e.timestart >= :windowstart OR 0 > :spanstart)/;
