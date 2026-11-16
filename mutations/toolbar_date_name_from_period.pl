# Name the date button with the filter alone, whatever period is set.
s/\x27datebuttonlabel\x27 => \$hasperiod \? self::date_button_name\(\$period\) : \$datelegend,/\x27datebuttonlabel\x27 => \$datelegend,/;
