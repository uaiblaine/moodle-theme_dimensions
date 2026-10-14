# Drop the declaration of the branding table from the metadata, so its user-id column is stored
# with nothing in the privacy provider saying so.
s/\$collection->add_database_table\(.*?\);\n//s;
