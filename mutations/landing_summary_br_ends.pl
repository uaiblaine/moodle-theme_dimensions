# Stop a line break from ending a paragraph, so a summary written as one block broken by <br> is never cut.
s/const ENDS = 'p, li, h1, h2, h3, h4, h5, h6, blockquote, pre, figure, table, br';/const ENDS = 'p, li, h1, h2, h3, h4, h5, h6, blockquote, pre, figure, table';/;
