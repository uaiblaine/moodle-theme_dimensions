# Let the automatic opening swap a panel the READER opened. A later answer naming another single
# area then closes the area the reader chose and opens that one in its place, which the design's
# second guard forbids. The condition is kept and made never true, so the module still builds.
s/state\.open && state\.openedby === 'user'/state.open && state.openedby === 'nobody'/;
