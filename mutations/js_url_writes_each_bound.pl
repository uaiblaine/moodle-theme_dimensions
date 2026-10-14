# Write the start into the address only beside an end, as the module did when a period was a pair:
# a start alone then narrows the listing while the address says nothing of it, and the next reload,
# share or back button loses it.
s/if \(state\.datefrom !== ''\) \{\n([ \t]+)params\.set\(PARAM\.from/if (state.datefrom !== '' && state.dateto !== '') {\n$1params.set(PARAM.from/;
