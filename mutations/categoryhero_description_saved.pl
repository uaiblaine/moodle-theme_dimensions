# Drop the save of the category's own description. The form still shows the field, the manager
# still edits it, the page still says "Category appearance saved." - and the description is the
# one thing on the form that was silently thrown away, on every save, for everybody.
s/    \$coursecat->update\(\n        \(object\) \['description_editor' => \$data->description_editor\],\n        categoryhero_form::description_editor_options\(\$id\)\n    \);\n//;
