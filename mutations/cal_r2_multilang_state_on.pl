# Read "Off, but available" as on: the visitor's names lose a language the logged-in page keeps.
s/\(int\) \$states\['multilang'\]->active === TEXTFILTER_ON/(int) \$states['multilang']->active !== TEXTFILTER_DISABLED/;
