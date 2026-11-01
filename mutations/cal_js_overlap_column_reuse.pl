# Never reuse a column at a touching end.
s/let column = ends\.findIndex\(\(last\) => last <= start\);/let column = ends.findIndex((last) => last < start);/;
