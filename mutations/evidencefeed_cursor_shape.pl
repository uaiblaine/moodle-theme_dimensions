# Read half a cursor as no cursor at all, so a request naming a time alone restarts the list.
s/\|\| \(\$beforetime === 0\) !== \(\$beforeid === 0\)\) \{/) {/;
