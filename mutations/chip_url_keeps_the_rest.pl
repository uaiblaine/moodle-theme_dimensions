# Build the chip's address from what "Clear all" keeps: pressing one chip drops every criterion.
s/(private function chip\(string \$param, string \$label, array \$drop\): array \{\n        \$params = \$this->criteria->)query_params\(\);/${1}keep_params();/;
