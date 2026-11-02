# Answer every item of the window, past the reader's count.
s/'items' => \$max > 0 \? array_slice\(\$kept, 0, \$max\) : \$kept,/'items' => \$kept,/;
