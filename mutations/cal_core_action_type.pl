# Read every activity event's row as an action event.
s/"id \{\$insql\} AND type = :action"/"id {\$insql} AND type >= 0 AND 1 <> :action"/;
