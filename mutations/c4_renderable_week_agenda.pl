# List the month's own days alone in the week's agenda.
s/if \(\$this->week !== '' \|\| str_starts_with\(\$this->window->days\[\$i\], \$this->month\)\) \{/if (str_starts_with(\$this->window->days[\$i], \$this->month)) {/;
