# Count a period as narrowing only when both of its bounds are set: the posters are then told a
# request with a start alone does not narrow, and their pills stop saying they are waiting for it.
s/\|\| state\.datefrom !== ''\n([ \t]+)\|\| state\.dateto !== '';/|| (state.datefrom !== '' && state.dateto !== '');/;
