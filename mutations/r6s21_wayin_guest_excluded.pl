# Count guest access as a way in through the login: it needs no login at all.
s/ \|\| \$type === 'guest'\) \{/ || false) {/
