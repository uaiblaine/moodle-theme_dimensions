# Link the bar to guest access only for a visitor, never on a logged-in reader's open card.
s/\(\$exported->isvisitor \|\| \$exported->isopen\)\) \{/(\$exported->isvisitor)) {/
