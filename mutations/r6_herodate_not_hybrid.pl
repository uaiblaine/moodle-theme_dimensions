# Give a hybrid event the online event's date block, which calls the whole event a broadcast.
s/ \|\| \$this->is_presencial\(\) \|\| \$this->is_hybrid\(\)\) \{/ || \$this->is_presencial()) {/;
