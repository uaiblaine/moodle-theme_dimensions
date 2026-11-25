# Drop the delete trigger's data-action: tool_lp/evidence_delete delegates on it, so the trash icon deletes nothing.
s/ data-action="delete-evidence">\{\{#pix\}\}t\/delete/>{{#pix}}t\/delete/;
