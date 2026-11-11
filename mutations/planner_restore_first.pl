# Start a child for every missing sheet: a new cron machine runs a process and a compile per flavour every minute.
s/\$code = \(\$this->restorer\)\(\$flavourid, \$direction\);/\$code = null;/;
