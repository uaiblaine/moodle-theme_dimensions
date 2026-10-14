# Rename the section that guards the block drawer, so the drawer is never rendered. This is
# the mod_book, mod_quiz and mod_lesson regression: those modules put their own navigation in
# a block, and on a mod_page - where most of the suite runs - the drawer is empty anyway.
s/\{\{#hasblocks\}\}/{{#hasnoblocks}}/g;
s/\{\{\/hasblocks\}\}/{{\/hasnoblocks}}/g;
