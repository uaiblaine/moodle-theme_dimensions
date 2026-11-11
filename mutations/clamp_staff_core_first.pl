# Stop dropping a hidden course the viewer may not see before it takes a place in the staff list:
# fifty such courses fill the cap and a visible course past them is withheld in the SQL cut.
s/if \(!\$row->visible && !\$seeshidden\) \{/if (false) {/;
