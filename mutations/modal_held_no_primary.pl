# Let an event already held offer the course dialogue's primary button: "Enrol" on an event that enrols nobody.
s/if \(\$this->anonymous \|\| \$held\) \{/if (\$this->anonymous) {/;
