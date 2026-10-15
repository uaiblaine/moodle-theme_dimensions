# Drop the delete link's data-action, leaving the JavaScript that registers it: tool_lp/evidence_delete
# delegates on that attribute, so the trash icon would be a link to "#" that deletes nothing.
s/ data-action="delete-evidence">/>/;
