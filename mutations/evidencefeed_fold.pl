# List another component's rows one by one instead of folding them into a line per month.
s/AND e\.desccomponent = :component AND/AND (e.desccomponent = :component OR 1 = 1) AND/;
