# A deletion for a list of people clears the category's stamp whoever it names.
s/"categoryid = :categoryid AND usermodified \$insql"/"categoryid = :categoryid AND (usermodified \$insql OR 1 = 1)"/;
