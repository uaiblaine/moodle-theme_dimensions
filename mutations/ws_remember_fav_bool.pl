# Declare the remembering service's favourites toggle as raw text: a value that is not a boolean is
# then stored as the toggle switched on.
s/('fav' => new external_value\(\s*)PARAM_BOOL/${1}PARAM_RAW/;
