# Offer every custom field type: a checkbox's flag or a date's timestamp could be chosen as a name.
s/WHERE f\.datatype \$insql/WHERE f.datatype <> :type1 OR f.datatype <> :type2 OR f.datatype <> :type3/;
