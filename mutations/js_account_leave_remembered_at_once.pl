# Leave only when the timer ends: the save is still sent at once, but the page waits the whole second
# even when the save was answered straight away.
s/^(\s*)remember\(true\),\n/$1(remember(true), new Promise(() => null)),\n/m;
