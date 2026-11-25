# Let the cursor's own row through again: the row on a page boundary is listed on two pages.
s/AND e\.id < :beforeid\)\)/AND e.id <= :beforeid))/;
