# Print a route beside any state the card is handed one for.
s/\n\s*&& in_array\(\$state, classifier::ROUTE_STATES, true\);/;/;
