# Hand the hero url to the template unguarded. clean_param(PARAM_URL) alone is not a guard:
# PARAM_URL passes a quote and a
# parenthesis, the template prints the url inside url('...') in a style attribute, and a browser
# decodes that attribute before the CSS parser reads it.
s/\$safe = styleurl::safe\(\(string\) \$url\);/\$safe = (string) \$url;/;
