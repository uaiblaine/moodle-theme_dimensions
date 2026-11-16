# Send "Clear period" to the listing as it stands, the period kept.
s/(private function without_period\(\): \\core\\url \{\n        \$params = \$this->criteria->query_params\(\);\n)        unset\(\$params\[criteria::PARAM_DATEFROM\], \$params\[criteria::PARAM_DATETO\]\);\n/$1/;
