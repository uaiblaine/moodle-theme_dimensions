# Leave the facets out of the rule that draws the Filters control.
s/\x27hasfiltergroups\x27 => !empty\(\$facets\) \|\| /\x27hasfiltergroups\x27 => /;
