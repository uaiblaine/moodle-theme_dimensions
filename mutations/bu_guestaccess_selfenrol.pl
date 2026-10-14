# The guest access hint links self enrolment only where an instance offers it.
s/            if \(\$plugins\[\$instance->enrol\]->show_enrolme_link\(\$instance\)\) \{/            if (false) {/;
