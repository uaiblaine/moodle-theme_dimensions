# Ask a batch before the first month from the month before it: the answer overlaps what is held and is refused.
s/fetch\(ahead \? target : monthAfter\(target, 1 - count\), count\)/fetch(target, count)/;
