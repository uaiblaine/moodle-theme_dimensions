# Stop reading the start from the page's address: a start alone sent by the no-JavaScript form, the
# visitor's form above all, then answers the unnarrowed listing with the field emptied.
s/'datefrom' => optional_param\(self::PARAM_DATEFROM, '', PARAM_RAW_TRIMMED\),/'datefrom' => '',/;
