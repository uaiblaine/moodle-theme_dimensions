# Read the toolbar form's id as a property: on the visitor's script form, whose hidden field is named "id",
# that is the field itself, and the component is handed {} and never draws or releases the line.
s/form \? form\.getAttribute\('id'\) : null/form ? form.id : null/;
