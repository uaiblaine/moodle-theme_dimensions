# Read a timestamp's date in UTC instead of the viewer's timezone.
s/->setTimezone\(\$timezone\)/->setTimezone(new \\DateTimeZone('UTC'))/;
