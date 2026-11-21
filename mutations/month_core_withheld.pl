# A withheld category's events are counted from core's context.
s/if \(\$categoryids\) \{\n(\s+\$listed = core_events::admitted_categories)/if (false) {\n$1/;
