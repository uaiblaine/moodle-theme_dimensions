# Keep the drop at the day zoom's offset on a narrow day, past the day it starts on.
s/b\.from \* day \+ Math\.min\(LINK\.stub, Math\.max\(LINK\.least, day \/ 2\)\)/b.from * day + LINK.stub/;
