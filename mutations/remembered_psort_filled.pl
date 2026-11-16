# Never fill the remembered grouped order into a bare address: the page opens the panels newest first.
s/        \$options\['psort'\] = \(string\) \(\$entry\[remembered::KEY_PSORT\] \?\? ''\);\n//;
