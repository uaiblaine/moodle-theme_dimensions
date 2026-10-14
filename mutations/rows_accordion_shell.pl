# Build the accordion's shell (the posters' call to action) on the logged-in shell.
s/category_panel::shell\(\$entry, \$criteria, \$criteria->anonymous, \$isroot\)/category_panel::shell(\$entry, \$criteria, false, \$isroot)/;
