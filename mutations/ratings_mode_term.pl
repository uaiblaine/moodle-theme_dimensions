# Drop the mode term of the predicate: a course the plugin no longer rates shows the ratings its row still counts.
s/'ratingmode <> :ratingnoone AND cntall > 0'/'ratingmode >= :ratingnoone AND cntall > 0'/;
