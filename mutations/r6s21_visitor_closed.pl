# Say nothing to a visitor with no way in at all: no closed sentence.
s/if \(\$exported->isvisitor && !\$exported->showlogin && !\$exported->hasmethods\) \{/if (false) {/
