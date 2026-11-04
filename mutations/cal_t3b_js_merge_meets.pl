# Keep a trail whose period no longer meets the months held.
s/\.filter\(\(trail\) => meets\(trail, from, to, zone\)\)/.filter(() => true)/;
