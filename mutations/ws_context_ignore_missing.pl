# Derive the category context with the default strictness: an id nothing holds then throws
# dml_missing_record_exception from get_record() instead of reaching the deliberate refusal,
# so "missing" and "withheld" answer with different classes and different messages - an
# existence oracle over every category id on the site.
s/coursecat::instance\(\$categoryid, IGNORE_MISSING\)/coursecat::instance(\$categoryid, MUST_EXIST)/;
