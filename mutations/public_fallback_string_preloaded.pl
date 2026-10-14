# Stop preloading the failure's sentence. The module then fetches it over the very network that may
# have failed, and a visitor gets the page load with nothing said.
s/\n\s*\$PAGE->requires->strings_for_js\(\[self::STRING_FALLBACK\], 'theme_dimensions'\);//;
