# Take an organisation as the order of a named grouping.
s/\$vocabulary = \$group === '' \? criteria::SORTS : array_merge\(\[''\], criteria::ORDERS\);/\$vocabulary = criteria::SORTS;/;
