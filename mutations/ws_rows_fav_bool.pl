# Declare the listing service's favourites toggle as raw text: a value that is not a boolean is no
# longer refused at the framework, and reaches the delegate as a truthy string.
s/('fav' => new external_value\(\s*)PARAM_BOOL/${1}PARAM_RAW/;
