# Declare a `direct` parameter on the service again. A request carrying that key is then accepted
# instead of refused as an unknown one, and the panel's whole-subtree population, which is the
# only one the service answers, no longer has a parameter that could narrow it unnoticed.
s/(\n\s+)'withcategories' => new external_value\(/$1'direct' => new external_value(PARAM_BOOL, 'x', VALUE_DEFAULT, false),$1'withcategories' => new external_value(/;
