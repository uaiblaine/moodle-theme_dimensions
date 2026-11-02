# Drop the count term of the predicate: a course nobody rated shows a rating of nothing.
s/'ratingmode <> :ratingnoone AND cntall > 0'/'ratingmode <> :ratingnoone AND cntall >= 0'/;
