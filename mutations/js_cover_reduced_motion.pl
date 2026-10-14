# Take the reduced-motion half off the hover guard, leaving the device query alone. A reader who
# asked the platform for less movement then gets a film started under their pointer on every card
# they pass - the one thing the setting exists to prevent - while every control still works, so
# nothing about the page looks wrong to anybody who did not ask for it.
s/!hoverable\(\) \|\| stilled\(\)/!hoverable()/;
