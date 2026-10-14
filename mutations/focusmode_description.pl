# Blank the activity information render outright instead of putting the description back. That
# render is the ONLY place the activity description appears, so the intro text of a page or a
# forum silently vanishes from the reading column while the page still looks correct - and every
# assertion that the activity information BOX is gone stays green, because it is still gone.
s/self::description_only\(\$headercontent, \$output\)/''/;
