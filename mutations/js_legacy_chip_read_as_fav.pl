# Read an older address's favourites chip as nothing on Back and Forward: the state asks for no
# favourites while the address still names them.
s/ \|\| params\.get\(PARAM\.chip\) === LEGACY_FAVOURITES;/;/;
