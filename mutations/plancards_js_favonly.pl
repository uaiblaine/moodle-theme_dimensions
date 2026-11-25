# Drop the favourites-only predicate: the star keeps every plan.
s/const faved = !state\.favonly \|\| meta\.favourite === true;/const faved = true;/;
