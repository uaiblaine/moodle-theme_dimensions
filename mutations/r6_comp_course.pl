# Split the workload of a hybrid event too, which has no composition.
s/if \(\$this->is_event\(\) \|\| !\$this->is_hybrid\(\) \|\| \$total === null/if (!\$this->is_hybrid() || \$total === null/;
