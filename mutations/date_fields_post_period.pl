# Rename the date layer's native start field, so a page without JavaScript posts no start.
s/(id="\{\{datefromid\}\}" )name="\{\{datefromname\}\}"/${1}name="start"/;
