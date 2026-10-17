# Let a visitor's course card state its start day, which it never prints.
s/\$hasstartday = !\$this->anonymous \|\| \(\$isevent && \$start > 0\);/\$hasstartday = true;/;
