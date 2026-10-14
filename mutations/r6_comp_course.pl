# Split the workload of a hybrid event too, which has no composition.
s/if \(\$this->is_evento\(\) \|\| !\$this->is_hybrid\(\) \|\| \$total === null/if (!\$this->is_hybrid() || \$total === null/;
