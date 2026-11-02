# Read an entry for an item of another source with the same id.
s/\(item\.source === 'core' \? edits\.get\(item\.id\) \|\| null : null\)/(edits.get(item.id) || null)/;
