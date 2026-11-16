# Count a hidden group as a group the panel shows.
s/\.some\(\(group\) => !group\.hidden\)/.some((group) => group !== null)/;
