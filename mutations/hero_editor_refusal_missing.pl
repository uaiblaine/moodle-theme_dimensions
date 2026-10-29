# Let the context lookup throw for an unused id: core's dml_missing_record_exception is a second answer beside
# the theme's refusal, and the difference tells a guesser which category ids exist.
s/\\core\\context\\coursecat::instance\(\$categoryid, IGNORE_MISSING\)/\\core\\context\\coursecat::instance(\$categoryid)/;
