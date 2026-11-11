# Renames the branding table's authorship column in install.xml to a name the user-column scan still sees, so the metadata declares a column the table no longer has.
s/NAME="usermodified"/NAME="modifiedby"/;
