# Drop the flat order from "see all as a list": the link reloads the grouped page it sits on, with
# the same posters, instead of the one list the sentence promises.
s/\$criteria->narrowing_params\(\) \+ \[criteria::PARAM_SORT => criteria::SORT_RECENT\],/\$criteria->narrowing_params(),/;
