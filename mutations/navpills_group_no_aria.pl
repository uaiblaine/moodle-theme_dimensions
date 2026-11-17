# Give the pill of the group that holds the current page aria-current too: two current pages in the row (D96).
s/<summary class="dim-navpill\{\{#current\}\} is-selected\{\{\/current\}\}">/<summary class="dim-navpill{{#current}} is-selected{{\/current}}"{{#current}} aria-current="page"{{\/current}}>/;
