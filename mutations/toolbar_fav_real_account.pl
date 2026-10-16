# Draw the favourites toggle wherever the state group is: the guest account then gets a star that
# narrows to favourites core never keeps for it.
s/if \(!\$this->showstates \|\| !criteria::can_favourite\(\$this->anonymous\)\) \{/if (!\$this->showstates) {/;
