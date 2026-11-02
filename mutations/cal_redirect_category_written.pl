# Read only the name core reads, not the one it writes into its own address.
s/ \?: \(int\) \(\$request\[.categoryid.\] \?\? 0\)//;
