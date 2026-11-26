# The summary counts every user, outside the report's base conditions and filters.
s/WHERE \{\$table->sql->where\}\) x"/WHERE 1 = 1) x"/;
